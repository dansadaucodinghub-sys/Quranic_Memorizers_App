<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Application;

use Qmdb\Modules\Community\Domain\ClipStatus;
use Qmdb\Modules\Identity\Infrastructure\Security\ContactCipher;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitAttempt;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitPolicy;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitScope;
use Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventSubjectKind;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequest;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequirementGuard;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationSubject;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\WorkspaceAuthorizationScope;
use Qmdb\Modules\TenancyContext\Domain\AccountWorkspaceTenantContext;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Time\Clock;

/** An owner may appeal once; an unconflicted second reviewer decides without erasing the first decision. */
final readonly class CommunityModerationAppealService
{
    public function __construct(
        private CommunityModerationAppealRepository $appeals,
        private RecitationClipRepository $clips,
        private CommunityOperationReceipts $receipts,
        private AuthorizationRequirementGuard $authorization,
        private StepUpGuard $stepUp,
        private IdentityRateLimiter $rateLimits,
        private IdentityFingerprintGenerator $fingerprints,
        private SecurityAuditEventAppender $audit,
        private CommunityNotificationIntentRepository $notifications,
        private TransactionManager $transactions,
        private Clock $clock,
        private ContactCipher $cipher,
    ) {
    }

    /** @return list<array{case_id:string,clip_id:string,action:string,decided_at:string}> */
    public function ownerEligible(
        AuthenticatedAccountContext $actor,
        AccountWorkspaceTenantContext $tenant,
    ): array {
        $this->requireContext($actor, $tenant);
        return $this->transactions->transactional(fn (): array => $this->appeals->ownerEligible(
            $tenant->workspaceInternalId,
            $actor->accountInternalId,
            $this->clock->now()->modify('-30 days'),
        ));
    }

    /** @return array{public_id:string,status:string,version:int} */
    public function submit(
        AuthenticatedAccountContext $actor,
        AccountWorkspaceTenantContext $tenant,
        UuidV7 $submission,
        UuidV7 $caseId,
        string $statement,
    ): array {
        $this->requireContext($actor, $tenant);
        $statement = trim($statement);
        if ($statement === '' || mb_strlen($statement) > 2000) {
            throw new \InvalidArgumentException('Appeal statement must be between 1 and 2000 characters.');
        }
        $fingerprint = hash('sha256', json_encode([$actor->accountInternalId,
            $tenant->workspaceInternalId, $caseId->toString(), 'APPEAL_SUBMIT'], JSON_THROW_ON_ERROR), true);
        $completed = $this->transactions->transactional(fn (): ?array => $this->receipts->completedForActor(
            $submission,
            $actor->accountInternalId,
            'APPEAL_SUBMIT',
            $fingerprint,
        ));
        if ($completed !== null) {
            return $completed;
        }
        $this->limit($actor);
        return $this->transactions->transactional(function () use (
            $actor,
            $tenant,
            $submission,
            $caseId,
            $statement,
            $fingerprint
        ): array {
            $now = $this->clock->now();
            $decision = $this->appeals->eligibleDecision(
                $tenant->workspaceInternalId,
                $caseId,
                $actor->accountInternalId,
            );
            if ($decision === null) {
                throw new \DomainException('Moderation action is not eligible for appeal.');
            }
            if ($now > $decision['decided_at']->modify('+30 days')) {
                throw new \DomainException('The moderation appeal window has closed.');
            }
            $replay = $this->receipts->claim(
                $submission,
                $tenant->workspaceInternalId,
                $actor->accountInternalId,
                'APPEAL_SUBMIT',
                $fingerprint,
                $now
            );
            if ($replay !== null) {
                return $replay;
            }
            $appealId = $this->appeals->submit(
                $decision,
                $actor->accountInternalId,
                $this->cipher->encrypt($statement),
                $this->cipher->keyId(),
                $now
            );
            $this->notifications->enqueue(
                $tenant->workspaceInternalId,
                $actor->accountInternalId,
                'APPEAL_RECEIVED',
                $appealId,
                1,
                'SUBMITTED',
                $now
            );
            $this->receipts->complete($submission, $appealId, 'SUBMITTED', 1, $now);
            $this->audit->workspace(
                SecurityEventCode::COMMUNITY_MODERATION_APPEALED,
                $tenant->workspacePublicId(),
                SecurityEventSubjectKind::COMMUNITY_MODERATION_CASE,
                $caseId->toString(),
                $actor->accountId->toString(),
                $now,
                ['appeal_public_id' => $appealId->toString(),
                'operation_public_id' => $submission->toString()],
                'APPEAL_SUBMITTED'
            );
            return ['public_id' => $appealId->toString(), 'status' => 'SUBMITTED', 'version' => 1];
        });
    }

    /** @return list<array{public_id:string,case_id:string,clip_id:string,submitted_at:string}> */
    public function queue(AuthenticatedAccountContext $actor, AccountWorkspaceTenantContext $tenant): array
    {
        $this->requireReviewer($actor, $tenant);
        return $this->transactions->transactional(fn (): array => $this->appeals->queue(
            $tenant->workspaceInternalId,
            $actor->accountInternalId,
        ));
    }

    public function statement(
        AuthenticatedAccountContext $actor,
        AccountWorkspaceTenantContext $tenant,
        UuidV7 $appealId,
    ): string {
        $this->requireReviewer($actor, $tenant);
        return $this->transactions->transactional(function () use ($actor, $tenant, $appealId): string {
            $row = $this->appeals->statement($tenant->workspaceInternalId, $appealId, $actor->accountInternalId);
            if ($row === null || !hash_equals($this->cipher->keyId(), $row['key_id'])) {
                throw new \DomainException('Appeal statement is unavailable.');
            }
            return $this->cipher->decrypt($row['packed_ciphertext']);
        });
    }

    /** @return array{public_id:string,status:string,version:int} */
    public function decide(
        AuthenticatedAccountContext $actor,
        AccountWorkspaceTenantContext $tenant,
        UuidV7 $submission,
        UuidV7 $appealId,
        int $expectedVersion,
        string $outcome,
        string $reasonCode,
    ): array {
        $this->requireReviewer($actor, $tenant);
        if (
            $expectedVersion !== 1 || !in_array($outcome, ['UPHELD', 'RESTORED'], true)
            || preg_match('/\A[A-Z][A-Z0-9_]{1,47}\z/', $reasonCode) !== 1
        ) {
            throw new \InvalidArgumentException('Appeal decision is invalid.');
        }
        $fingerprint = hash('sha256', json_encode([$actor->accountInternalId,
            $tenant->workspaceInternalId, $appealId->toString(), $expectedVersion,
            $outcome, $reasonCode], JSON_THROW_ON_ERROR), true);
        $completed = $this->transactions->transactional(fn (): ?array => $this->receipts->completedForActor(
            $submission,
            $actor->accountInternalId,
            'APPEAL_DECIDE',
            $fingerprint,
        ));
        if ($completed !== null) {
            return $completed;
        }
        $this->limit($actor);
        return $this->transactions->transactional(function () use (
            $actor,
            $tenant,
            $submission,
            $appealId,
            $expectedVersion,
            $outcome,
            $reasonCode,
            $fingerprint
        ): array {
            $now = $this->clock->now();
            $replay = $this->receipts->claim(
                $submission,
                $tenant->workspaceInternalId,
                $actor->accountInternalId,
                'APPEAL_DECIDE',
                $fingerprint,
                $now
            );
            if ($replay !== null) {
                return $replay;
            }
            $appeal = $this->appeals->lock(
                $tenant->workspaceInternalId,
                $appealId,
                $actor->accountInternalId
            );
            if (
                $appeal === null || $appeal['status'] !== 'SUBMITTED'
                || $appeal['version'] !== $expectedVersion
                || $appeal['appellant_account_id'] === $actor->accountInternalId
                || $appeal['decision_reviewer_id'] === $actor->accountInternalId
            ) {
                throw new \DomainException('Appeal is unavailable for independent review.');
            }
            $this->stepUp->consumeWithGrant($actor, StepUpAction::COMMUNITY_MODERATION_DECIDE);
            $this->appeals->decide($appeal, $actor->accountInternalId, $outcome, $reasonCode, $now);
            if ($outcome === 'RESTORED') {
                $clip = $this->clips->lock($tenant->workspaceInternalId, $appeal['clip_id']);
                if ($clip === null) {
                    throw new \DomainException('Appealed Clip is unavailable.');
                }
                if ($clip->status === ClipStatus::REMOVED) {
                    $clip = $this->clips->transition(
                        $clip,
                        ClipStatus::HIDDEN,
                        $actor->accountInternalId,
                        'APPEAL_RESTORATION_STAGE',
                        $now
                    );
                }
                $clip->status->assertTransition(ClipStatus::PUBLISHED);
                $this->clips->publicationEvidence($clip)->assertPubliclyEligible();
                $this->clips->transition(
                    $clip,
                    ClipStatus::PUBLISHED,
                    $actor->accountInternalId,
                    'APPEAL_RESTORED',
                    $now
                );
            }
            $this->notifications->enqueue(
                $tenant->workspaceInternalId,
                $appeal['appellant_account_id'],
                'APPEAL_DECISION',
                $appealId,
                $expectedVersion + 1,
                $outcome,
                $now
            );
            $this->receipts->complete($submission, $appealId, $outcome, $expectedVersion + 1, $now);
            $this->audit->workspace(
                SecurityEventCode::COMMUNITY_MODERATION_APPEAL_DECIDED,
                $tenant->workspacePublicId(),
                SecurityEventSubjectKind::COMMUNITY_MODERATION_CASE,
                $appealId->toString(),
                $actor->accountId->toString(),
                $now,
                ['outcome_code' => $outcome, 'reason_code' => $reasonCode,
                'operation_public_id' => $submission->toString()],
                $reasonCode
            );
            return ['public_id' => $appealId->toString(), 'status' => $outcome,
                'version' => $expectedVersion + 1];
        });
    }

    private function requireReviewer(
        AuthenticatedAccountContext $actor,
        AccountWorkspaceTenantContext $tenant,
    ): void {
        $this->requireContext($actor, $tenant);
        $this->authorization->requireAllowed(new AuthorizationRequest(
            AuthorizationSubject::fromAuthenticatedContext($actor),
            new PermissionCode('workspace.community.moderation.decide'),
            new WorkspaceAuthorizationScope($tenant),
        ));
    }

    private function requireContext(
        AuthenticatedAccountContext $actor,
        AccountWorkspaceTenantContext $tenant,
    ): void {
        if (
            $actor->accountInternalId !== $tenant->accountInternalId
            || $actor->sessionInternalId !== $tenant->sessionInternalId
        ) {
            throw new \DomainException('Appeal context does not match the authenticated session.');
        }
    }

    private function limit(AuthenticatedAccountContext $actor): void
    {
        $result = $this->rateLimits->consume([new IdentityRateLimitAttempt(
            IdentityRateLimitScope::COMMUNITY_MUTATION_ACCOUNT,
            $this->fingerprints->generate('community-appeal-account', (string) $actor->accountInternalId),
            new IdentityRateLimitPolicy(60, 10, 60),
        )], $this->clock->now());
        if (!$result->allowed) {
            throw new \DomainException('Appeal rate limit exceeded.');
        }
    }
}
