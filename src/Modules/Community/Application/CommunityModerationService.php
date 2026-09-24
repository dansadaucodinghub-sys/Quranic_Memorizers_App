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

/** Independent, assigned moderation; P9 technical media and official results remain untouched. */
final readonly class CommunityModerationService
{
    public function __construct(
        private CommunityModerationRepository $cases,
        private RecitationClipRepository $clips,
        private CommunityCommentRepository $comments,
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

    /** @return list<array{public_id:string,clip_id:string,body:string,version:int,created_at:string}> */
    public function heldComments(
        AuthenticatedAccountContext $actor,
        AccountWorkspaceTenantContext $tenant,
    ): array {
        $this->requireActor($actor, $tenant, 'workspace.community.moderation.decide');
        return $this->transactions->transactional(fn (): array => $this->comments->heldQueue(
            $tenant->workspaceInternalId,
            $actor->accountInternalId
        ));
    }

    /** @return array{public_id:string,status:string,version:int} */
    public function decideComment(
        AuthenticatedAccountContext $actor,
        AccountWorkspaceTenantContext $tenant,
        UuidV7 $submission,
        UuidV7 $commentId,
        int $expectedVersion,
        string $action,
    ): array {
        $this->requireActor($actor, $tenant, 'workspace.community.moderation.decide');
        if ($expectedVersion < 1 || !in_array($action, ['APPROVE', 'REMOVE'], true)) {
            throw new \InvalidArgumentException('Comment moderation action is invalid.');
        }
        $fingerprint = hash('sha256', json_encode([$tenant->workspaceInternalId,
            $actor->accountInternalId, $commentId->toString(), $expectedVersion,
            $action], JSON_THROW_ON_ERROR), true);
        $completed = $this->transactions->transactional(fn (): ?array => $this->receipts->completedForActor(
            $submission,
            $actor->accountInternalId,
            'COMMENT_MODERATE',
            $fingerprint
        ));
        if ($completed !== null) {
            return $completed;
        }
        $this->limit($actor);
        return $this->transactions->transactional(function () use (
            $actor,
            $tenant,
            $submission,
            $commentId,
            $expectedVersion,
            $action,
            $fingerprint
        ): array {
            $now = $this->clock->now();
            $replay = $this->receipts->claim(
                $submission,
                $tenant->workspaceInternalId,
                $actor->accountInternalId,
                'COMMENT_MODERATE',
                $fingerprint,
                $now
            );
            if ($replay !== null) {
                return $replay;
            }
            $this->stepUp->consumeWithGrant($actor, StepUpAction::COMMUNITY_MODERATION_DECIDE);
            $result = $this->comments->moderateHeld(
                $tenant->workspaceInternalId,
                $commentId,
                $actor->accountInternalId,
                $action,
                $expectedVersion,
                $now
            );
            $this->receipts->complete($submission, $commentId, $result['status'], $result['version'], $now);
            return $result;
        });
    }

    /** @return list<array{public_id:string,clip_id:string,asset_id:string,clip_status:string,status:string,priority:string,version:int,assigned_to_me:bool,report_count:int}> */
    public function queue(AuthenticatedAccountContext $actor, AccountWorkspaceTenantContext $tenant): array
    {
        $this->requireActor($actor, $tenant, 'workspace.community.moderation.review');
        return $this->transactions->transactional(fn (): array => $this->cases->queue(
            $tenant->workspaceInternalId,
            $actor->accountInternalId
        ));
    }

    /** @return list<array{reason:string,statement:string,created_at:string}> */
    public function caseReports(
        AuthenticatedAccountContext $actor,
        AccountWorkspaceTenantContext $tenant,
        UuidV7 $caseId
    ): array {
        $this->requireActor($actor, $tenant, 'workspace.community.moderation.review');
        $this->requireActor($actor, $tenant, 'workspace.community.reports.view');
        return $this->transactions->transactional(function () use ($actor, $tenant, $caseId): array {
            $reports = $this->cases->caseReports(
                $tenant->workspaceInternalId,
                $caseId,
                $actor->accountInternalId
            );
            $result = [];
            foreach ($reports as $report) {
                if (!hash_equals($this->cipher->keyId(), $report['key_id'])) {
                    throw new \DomainException('Confidential report key is unavailable.');
                }
                $result[] = ['reason' => $report['reason'],
                    'statement' => $this->cipher->decrypt($report['packed_ciphertext']),
                    'created_at' => $report['created_at']];
            }
            return $result;
        });
    }

    /** @return array{public_id:string,status:string,version:int} */
    public function assignSelf(
        AuthenticatedAccountContext $actor,
        AccountWorkspaceTenantContext $tenant,
        UuidV7 $submission,
        UuidV7 $caseId,
        int $expectedVersion
    ): array {
        $this->requireActor($actor, $tenant, 'workspace.community.moderation.review');
        return $this->mutate(
            $actor,
            $tenant,
            $submission,
            $caseId,
            $expectedVersion,
            'ASSIGN_SELF',
            null,
            null
        );
    }

    /** @return array{public_id:string,status:string,version:int} */
    public function startReview(
        AuthenticatedAccountContext $actor,
        AccountWorkspaceTenantContext $tenant,
        UuidV7 $submission,
        UuidV7 $caseId,
        int $expectedVersion
    ): array {
        $this->requireActor($actor, $tenant, 'workspace.community.moderation.review');
        return $this->mutate(
            $actor,
            $tenant,
            $submission,
            $caseId,
            $expectedVersion,
            'START_REVIEW',
            null,
            null
        );
    }

    /** @return array{public_id:string,status:string,version:int} */
    public function decide(
        AuthenticatedAccountContext $actor,
        AccountWorkspaceTenantContext $tenant,
        UuidV7 $submission,
        UuidV7 $caseId,
        int $expectedVersion,
        string $action,
        string $reasonCode
    ): array {
        $this->requireActor($actor, $tenant, 'workspace.community.moderation.decide');
        if (
            !in_array($action, ['NO_ACTION', 'HIDE', 'REMOVE', 'RESTORE', 'ESCALATE'], true)
            || preg_match('/\A[A-Z][A-Z0-9_]{1,47}\z/', $reasonCode) !== 1
        ) {
            throw new \InvalidArgumentException('Moderation action or reason is invalid.');
        }
        return $this->mutate(
            $actor,
            $tenant,
            $submission,
            $caseId,
            $expectedVersion,
            'DECIDE',
            $action,
            $reasonCode
        );
    }

    /** @return array{public_id:string,status:string,version:int} */
    private function mutate(
        AuthenticatedAccountContext $actor,
        AccountWorkspaceTenantContext $tenant,
        UuidV7 $submission,
        UuidV7 $caseId,
        int $expectedVersion,
        string $operation,
        ?string $action,
        ?string $reason
    ): array {
        if ($expectedVersion < 1) {
            throw new \InvalidArgumentException('Expected moderation version must be positive.');
        }
        $fingerprint = hash('sha256', json_encode([$tenant->workspaceInternalId,
            $actor->accountInternalId, $caseId->toString(), $expectedVersion,
            $operation, $action, $reason], JSON_THROW_ON_ERROR), true);
        $completed = $this->transactions->transactional(fn (): ?array => $this->receipts->completedForActor(
            $submission,
            $actor->accountInternalId,
            $operation,
            $fingerprint
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
            $expectedVersion,
            $operation,
            $action,
            $reason,
            $fingerprint
): array {
            $now = $this->clock->now();
            $replay = $this->receipts->claim(
                $submission,
                $tenant->workspaceInternalId,
                $actor->accountInternalId,
                $operation,
                $fingerprint,
                $now
            );
            if ($replay !== null) {
                return $replay;
            }
            $case = $this->cases->lock($tenant->workspaceInternalId, $caseId);
            if ($case === null) {
                throw new \DomainException('Moderation case is unavailable.');
            }
            if ($case['version'] !== $expectedVersion) {
                throw new \DomainException('Moderation case changed. Reload before trying again.');
            }
            if ($case['creator_account_id'] === $actor->accountInternalId) {
                throw new \DomainException('Creator cannot independently moderate their own content.');
            }
            $status = match ($operation) {
                'ASSIGN_SELF' => $this->assign($case, $actor->accountInternalId, $now),
                'START_REVIEW' => $this->start($case, $actor->accountInternalId, $now),
                'DECIDE' => $this->decision($case, $actor, $action, $reason, $now),
                default => throw new \LogicException('Unexpected moderation operation.'),
            };
            $this->receipts->complete($submission, $caseId, $status, $expectedVersion + 1, $now);
            if ($operation === 'DECIDE') {
                $this->notifications->enqueue(
                    $tenant->workspaceInternalId,
                    $case['creator_account_id'],
                    'MODERATION_DECISION',
                    $caseId,
                    $expectedVersion + 1,
                    $action ?? 'NO_ACTION',
                    $now
                );
                $this->audit->workspace(
                    SecurityEventCode::COMMUNITY_MODERATION_DECIDED,
                    $tenant->workspacePublicId(),
                    SecurityEventSubjectKind::COMMUNITY_MODERATION_CASE,
                    $caseId->toString(),
                    $actor->accountId->toString(),
                    $now,
                    ['action_code' => $action, 'reason_code' => $reason,
                        'case_version' => $expectedVersion + 1,
                    'operation_public_id' => $submission->toString()],
                    $reason
                );
            }
            return ['public_id' => $caseId->toString(), 'status' => $status,
                'version' => $expectedVersion + 1];
        });
    }

    /** @param array{id:int,public_id:UuidV7,workspace_id:int,clip_public_id:UuidV7,creator_account_id:int,status:string,version:int,assigned_reviewer_id:?int} $case */
    private function assign(array $case, int $actor, \DateTimeImmutable $now): string
    {
        $this->cases->assignSelf($case, $actor, $now);
        return 'ASSIGNED';
    }

    /** @param array{id:int,public_id:UuidV7,workspace_id:int,clip_public_id:UuidV7,creator_account_id:int,status:string,version:int,assigned_reviewer_id:?int} $case */
    private function start(array $case, int $actor, \DateTimeImmutable $now): string
    {
        $this->cases->startReview($case, $actor, $now);
        return 'UNDER_REVIEW';
    }

    /** @param array{id:int,public_id:UuidV7,workspace_id:int,clip_public_id:UuidV7,creator_account_id:int,status:string,version:int,assigned_reviewer_id:?int} $case */
    private function decision(
        array $case,
        AuthenticatedAccountContext $actor,
        ?string $action,
        ?string $reason,
        \DateTimeImmutable $now
    ): string {
        if ($action === null || $reason === null) {
            throw new \LogicException('Moderation decision is incomplete.');
        }
        $clip = $this->clips->lock($case['workspace_id'], $case['clip_public_id']);
        if ($clip === null) {
            throw new \DomainException('Moderated Clip is unavailable.');
        }
        $target = match ($action) {
            'HIDE' => ClipStatus::HIDDEN,
            'REMOVE' => ClipStatus::REMOVED,
            'RESTORE' => ClipStatus::PUBLISHED,
            'NO_ACTION', 'ESCALATE' => null,
            default => throw new \DomainException('Unsupported moderation decision.'),
        };
        if ($target !== null) {
            $clip->status->assertTransition($target);
        }
        $this->stepUp->consumeWithGrant($actor, StepUpAction::COMMUNITY_MODERATION_DECIDE);
        if ($target === ClipStatus::PUBLISHED) {
            // The case must be closed before the live eligibility check. Any
            // failed evidence check rolls the decision and close back together.
            $status = $this->cases->decide($case, $actor->accountInternalId, $action, $reason, $now);
            $this->clips->publicationEvidence($clip)->assertPubliclyEligible();
            $this->clips->transition(
                $clip,
                $target,
                $actor->accountInternalId,
                'MODERATION_' . $action,
                $now
            );
            return $status;
        }
        if ($target !== null) {
            $this->clips->transition(
                $clip,
                $target,
                $actor->accountInternalId,
                'MODERATION_' . $action,
                $now
            );
        }
        return $this->cases->decide($case, $actor->accountInternalId, $action, $reason, $now);
    }

    private function requireActor(
        AuthenticatedAccountContext $actor,
        AccountWorkspaceTenantContext $tenant,
        string $permission
    ): void {
        if (
            $actor->accountInternalId !== $tenant->accountInternalId
            || $actor->sessionInternalId !== $tenant->sessionInternalId
        ) {
            throw new \DomainException('Moderation context does not match the authenticated session.');
        }
        $this->authorization->requireAllowed(new AuthorizationRequest(
            AuthorizationSubject::fromAuthenticatedContext($actor),
            new PermissionCode($permission),
            new WorkspaceAuthorizationScope($tenant),
        ));
    }

    private function limit(AuthenticatedAccountContext $actor): void
    {
        $result = $this->rateLimits->consume([new IdentityRateLimitAttempt(
            IdentityRateLimitScope::COMMUNITY_MUTATION_ACCOUNT,
            $this->fingerprints->generate('community-moderation-account', (string) $actor->accountInternalId),
            new IdentityRateLimitPolicy(60, 20, 60),
        )], $this->clock->now());
        if (!$result->allowed) {
            throw new \DomainException('Moderation rate limit exceeded.');
        }
    }
}
