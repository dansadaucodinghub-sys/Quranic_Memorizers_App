<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Application;

use Qmdb\Modules\Community\Domain\ClipStatus;
use Qmdb\Modules\Community\Domain\RecitationClip;
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

/** P10 mutation boundary. Caller-supplied IDs select resources, never authority. */
final readonly class RecitationClipService
{
    public function __construct(
        private RecitationClipRepository $repository,
        private CommunityOperationReceipts $receipts,
        private AuthorizationRequirementGuard $authorization,
        private StepUpGuard $stepUp,
        private IdentityRateLimiter $rateLimits,
        private IdentityFingerprintGenerator $fingerprints,
        private SecurityAuditEventAppender $audit,
        private CommunityNotificationIntentRepository $notifications,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {
    }

    /** @return array{clips:list<array{public_id:string,status:string,version:int,caption:string,language:string,comment_policy:string,created_at:string,supersedes_public_id:?string}>,media:list<array{asset_id:string,variant_id:string,media_kind:string}>,passages:list<array{release_id:string,surah_number:int,ayah_count:int,arabic_name:string,english_name:string}>} */
    public function creatorWorkspace(AuthenticatedAccountContext $actor, AccountWorkspaceTenantContext $tenant): array
    {
        $this->assertContext($actor, $tenant);
        $this->requirePermission($actor, $tenant, 'community.clips.create');
        return $this->transactions->transactional(fn (): array => [
            'clips' => $this->repository->listOwn($tenant->workspaceInternalId, $actor->accountInternalId),
            'media' => $this->repository->eligibleMedia($tenant->workspaceInternalId, $actor->accountInternalId),
            'passages' => $this->repository->availablePassages(),
        ]);
    }

    /** @return list<array{public_id:string,asset_id:string,version:int,caption:string,language:string,created_at:string,surah:int,start:int,end:int,supersedes_public_id:?string}> */
    public function reviewQueue(AuthenticatedAccountContext $actor, AccountWorkspaceTenantContext $tenant): array
    {
        $this->assertContext($actor, $tenant);
        $this->requirePermission($actor, $tenant, 'workspace.community.clips.review');
        return $this->transactions->transactional(fn (): array => $this->repository->reviewQueue(
            $tenant->workspaceInternalId,
            $actor->accountInternalId
        ));
    }

    /** @return array{public_id:string,status:string,version:int} */
    public function createFromSelection(
        AuthenticatedAccountContext $actor,
        AccountWorkspaceTenantContext $tenant,
        UuidV7 $submission,
        UuidV7 $assetId,
        UuidV7 $variantId,
        UuidV7 $releaseId,
        int $surahNumber,
        int $startNumber,
        int $endNumber,
        string $caption,
        string $language,
        ?UuidV7 $supersedesClipId = null,
    ): array {
        $this->assertContext($actor, $tenant);
        $this->requirePermission($actor, $tenant, 'community.clips.create');
        $passage = $this->transactions->transactional(fn (): array => $this->repository->resolvePassage(
            $releaseId,
            $surahNumber,
            $startNumber,
            $endNumber
        ));
        return $this->createDraft(
            $actor,
            $tenant,
            $submission,
            $assetId,
            $variantId,
            $releaseId,
            $passage['start'],
            $passage['end'],
            $caption,
            $language,
            $supersedesClipId
        );
    }

    /** @return array{public_id:string,status:string,version:int} */
    public function createDraft(
        AuthenticatedAccountContext $actor,
        AccountWorkspaceTenantContext $tenant,
        UuidV7 $submission,
        UuidV7 $assetId,
        UuidV7 $variantId,
        UuidV7 $releaseId,
        UuidV7 $startAyahId,
        UuidV7 $endAyahId,
        string $caption,
        string $language,
        ?UuidV7 $supersedesClipId = null,
    ): array {
        $this->assertContext($actor, $tenant);
        $this->assertCaption($caption, $language);
        $this->requirePermission($actor, $tenant, 'community.clips.create');
        $fingerprint = $this->fingerprint('CREATE_DRAFT', [$tenant->workspaceInternalId,
            $actor->accountInternalId, $assetId->toString(), $variantId->toString(),
            $releaseId->toString(), $startAyahId->toString(), $endAyahId->toString(), $caption, $language,
            $supersedesClipId?->toString() ?? '']);
        $completed = $this->transactions->transactional(fn (): ?array => $this->receipts->completedForActor(
            $submission,
            $actor->accountInternalId,
            'CREATE_DRAFT',
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
            $fingerprint,
            $assetId,
            $variantId,
            $releaseId,
            $startAyahId,
            $endAyahId,
            $caption,
            $language,
            $supersedesClipId
): array {
            $now = $this->clock->now();
            $replay = $this->receipts->claim(
                $submission,
                $tenant->workspaceInternalId,
                $actor->accountInternalId,
                'CREATE_DRAFT',
                $fingerprint,
                $now
            );
            if ($replay !== null) {
                return $replay;
            }
            $clip = $this->repository->createDraft(
                $tenant->workspaceInternalId,
                $actor->accountInternalId,
                $assetId,
                $variantId,
                $releaseId,
                $startAyahId,
                $endAyahId,
                $caption,
                $language,
                $now,
                $supersedesClipId
            );
            $this->receipts->complete($submission, $clip->publicId, $clip->status->value, $clip->version, $now);
            return $this->result($clip);
        });
    }

    /** @return array{public_id:string,status:string,version:int} */
    public function submit(
        AuthenticatedAccountContext $actor,
        AccountWorkspaceTenantContext $tenant,
        UuidV7 $submission,
        UuidV7 $clipId,
        int $expectedVersion,
    ): array {
        $this->assertContext($actor, $tenant);
        $this->requirePermission($actor, $tenant, 'community.clips.manage_own');
        return $this->transition($actor, $tenant, $submission, $clipId, $expectedVersion, ClipStatus::REVIEW_PENDING, 'CREATOR_SUBMIT');
    }

    /** @return array{public_id:string,status:string,version:int} */
    public function publish(
        AuthenticatedAccountContext $actor,
        AccountWorkspaceTenantContext $tenant,
        UuidV7 $submission,
        UuidV7 $clipId,
        int $expectedVersion,
    ): array {
        $this->assertContext($actor, $tenant);
        $this->requirePermission($actor, $tenant, 'workspace.community.clips.review');
        return $this->transition($actor, $tenant, $submission, $clipId, $expectedVersion, ClipStatus::PUBLISHED, 'INDEPENDENT_REVIEW');
    }

    /** @return array{public_id:string,status:string,version:int} */
    public function updateDraft(
        AuthenticatedAccountContext $actor,
        AccountWorkspaceTenantContext $tenant,
        UuidV7 $submission,
        UuidV7 $clipId,
        int $expectedVersion,
        string $caption,
        string $language,
        string $commentPolicy = 'DISABLED'
    ): array {
        $this->assertContext($actor, $tenant);
        $this->requirePermission($actor, $tenant, 'community.clips.manage_own');
        $this->assertCaption($caption, $language);
        if (!in_array($commentPolicy, ['DISABLED', 'REVIEW', 'ENABLED'], true)) {
            throw new \InvalidArgumentException('Clip comment policy is invalid.');
        }
        if ($expectedVersion < 1) {
            throw new \InvalidArgumentException('Expected Clip version must be positive.');
        }
        $fingerprint = $this->fingerprint('UPDATE_DRAFT', [$tenant->workspaceInternalId,
            $actor->accountInternalId, $clipId->toString(), $expectedVersion, $caption, $language,
            $commentPolicy]);
        $completed = $this->transactions->transactional(fn (): ?array => $this->receipts->completedForActor(
            $submission,
            $actor->accountInternalId,
            'UPDATE_DRAFT',
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
            $clipId,
            $expectedVersion,
            $caption,
            $language,
            $commentPolicy,
            $fingerprint
): array {
            $now = $this->clock->now();
            $replay = $this->receipts->claim(
                $submission,
                $tenant->workspaceInternalId,
                $actor->accountInternalId,
                'UPDATE_DRAFT',
                $fingerprint,
                $now
            );
            if ($replay !== null) {
                return $replay;
            }
            $clip = $this->repository->lock($tenant->workspaceInternalId, $clipId);
            if ($clip === null || $clip->version !== $expectedVersion) {
                throw new \DomainException('Clip changed or is unavailable.');
            }
            $updated = $this->repository->updateDraft(
                $clip,
                $actor->accountInternalId,
                $caption,
                $language,
                $now,
                $commentPolicy
            );
            $this->receipts->complete(
                $submission,
                $updated->publicId,
                $updated->status->value,
                $updated->version,
                $now
            );
            return $this->result($updated);
        });
    }

    /** @return array{public_id:string,status:string,version:int} */
    public function hideOwn(
        AuthenticatedAccountContext $actor,
        AccountWorkspaceTenantContext $tenant,
        UuidV7 $submission,
        UuidV7 $clipId,
        int $expectedVersion
    ): array {
        $this->assertContext($actor, $tenant);
        $this->requirePermission($actor, $tenant, 'community.clips.manage_own');
        return $this->transition(
            $actor,
            $tenant,
            $submission,
            $clipId,
            $expectedVersion,
            ClipStatus::HIDDEN,
            'CREATOR_HIDE'
        );
    }

    /** @return array{public_id:string,status:string,version:int} */
    public function removeOwn(
        AuthenticatedAccountContext $actor,
        AccountWorkspaceTenantContext $tenant,
        UuidV7 $submission,
        UuidV7 $clipId,
        int $expectedVersion
    ): array {
        $this->assertContext($actor, $tenant);
        $this->requirePermission($actor, $tenant, 'community.clips.manage_own');
        return $this->transition(
            $actor,
            $tenant,
            $submission,
            $clipId,
            $expectedVersion,
            ClipStatus::REMOVED,
            'CREATOR_REMOVE'
        );
    }

    /** @return array{public_id:string,status:string,version:int} */
    public function archiveOwn(
        AuthenticatedAccountContext $actor,
        AccountWorkspaceTenantContext $tenant,
        UuidV7 $submission,
        UuidV7 $clipId,
        int $expectedVersion
    ): array {
        $this->assertContext($actor, $tenant);
        $this->requirePermission($actor, $tenant, 'community.clips.manage_own');
        return $this->transition(
            $actor,
            $tenant,
            $submission,
            $clipId,
            $expectedVersion,
            ClipStatus::ARCHIVED,
            'CREATOR_ARCHIVE'
        );
    }

    /** @return array{public_id:string,status:string,version:int} */
    private function transition(
        AuthenticatedAccountContext $actor,
        AccountWorkspaceTenantContext $tenant,
        UuidV7 $submission,
        UuidV7 $clipId,
        int $expectedVersion,
        ClipStatus $target,
        string $reason,
    ): array {
        if ($expectedVersion < 1) {
            throw new \InvalidArgumentException('Expected Clip version must be positive.');
        }
        $fingerprint = $this->fingerprint($reason, [$tenant->workspaceInternalId,
            $actor->accountInternalId, $clipId->toString(), $expectedVersion]);
        $completed = $this->transactions->transactional(fn (): ?array => $this->receipts->completedForActor(
            $submission,
            $actor->accountInternalId,
            $reason,
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
            $clipId,
            $expectedVersion,
            $target,
            $reason,
            $fingerprint,
        ): array {
            $now = $this->clock->now();
            $replay = $this->receipts->claim(
                $submission,
                $tenant->workspaceInternalId,
                $actor->accountInternalId,
                $reason,
                $fingerprint,
                $now
            );
            if ($replay !== null) {
                return $replay;
            }
            $clip = $this->repository->lock($tenant->workspaceInternalId, $clipId);
            if ($clip === null) {
                throw new \DomainException('Clip is unavailable.');
            }
            if ($clip->version !== $expectedVersion) {
                throw new \DomainException('Clip changed. Reload before trying again.');
            }
            $clip->status->assertTransition($target);
            if (
                in_array($reason, ['CREATOR_SUBMIT', 'CREATOR_HIDE', 'CREATOR_REMOVE', 'CREATOR_ARCHIVE'], true)
                && $clip->creatorAccountId !== $actor->accountInternalId
            ) {
                throw new \DomainException('Only the creator may perform this Clip action.');
            }
            if ($target === ClipStatus::PUBLISHED) {
                if ($clip->creatorAccountId === $actor->accountInternalId) {
                    throw new \DomainException('A creator cannot independently publish their own Clip.');
                }
                $this->repository->publicationEvidence($clip)->assertPubliclyEligible();
                $this->stepUp->consumeWithGrant($actor, StepUpAction::CLIP_PUBLISH);
            }
            $supersededSourceId = $target === ClipStatus::PUBLISHED
                ? $this->repository->supersededSourceId($clip) : null;
            $updated = $this->repository->transition($clip, $target, $actor->accountInternalId, $reason, $now);
            $notificationType = match ($target) {
                ClipStatus::REVIEW_PENDING => 'CLIP_REVIEW_REQUESTED',
                ClipStatus::PUBLISHED => 'CLIP_PUBLISHED',
                ClipStatus::HIDDEN => 'CLIP_HIDDEN',
                ClipStatus::REMOVED => 'CLIP_REMOVED',
                default => null,
            };
            if ($notificationType !== null) {
                $this->notifications->enqueue(
                    $tenant->workspaceInternalId,
                    $clip->creatorAccountId,
                    $notificationType,
                    $updated->publicId,
                    $updated->version,
                    $updated->status->value,
                    $now
                );
            }
            $this->receipts->complete($submission, $updated->publicId, $updated->status->value, $updated->version, $now);
            if ($target === ClipStatus::PUBLISHED) {
                $this->audit->workspace(
                    SecurityEventCode::COMMUNITY_CLIP_PUBLISHED,
                    $tenant->workspacePublicId(),
                    SecurityEventSubjectKind::RECITATION_CLIP,
                    $updated->publicId->toString(),
                    $actor->accountId->toString(),
                    $now,
                    ['clip_version' => $updated->version, 'operation_public_id' => $submission->toString()]
                        + ($supersededSourceId === null ? []
                            : ['supersedes_clip_public_id' => $supersededSourceId->toString()]),
                    $reason
                );
            }
            return $this->result($updated);
        });
    }

    /** @param list<int|string> $parts */
    private function fingerprint(string $operation, array $parts): string
    {
        return hash('sha256', json_encode([$operation, ...$parts], JSON_THROW_ON_ERROR), true);
    }

    private function assertCaption(string $caption, string $language): void
    {
        if (
            mb_strlen($caption) > 2000 || str_contains($caption, "\0")
            || strip_tags($caption) !== $caption || !in_array($language, ['ar', 'en'], true)
        ) {
            throw new \InvalidArgumentException('Clip caption or language is invalid.');
        }
    }

    private function limit(AuthenticatedAccountContext $actor): void
    {
        $result = $this->rateLimits->consume([new IdentityRateLimitAttempt(
            IdentityRateLimitScope::COMMUNITY_MUTATION_ACCOUNT,
            $this->fingerprints->generate('community-mutation-account', (string) $actor->accountInternalId),
            new IdentityRateLimitPolicy(60, 30, 60),
        )], $this->clock->now());
        if (!$result->allowed) {
            throw new \DomainException('Community mutation rate limit exceeded.');
        }
    }

    /** @return array{public_id:string,status:string,version:int} */
    private function result(RecitationClip $clip): array
    {
        return ['public_id' => $clip->publicId->toString(), 'status' => $clip->status->value,
            'version' => $clip->version];
    }

    private function assertContext(AuthenticatedAccountContext $actor, AccountWorkspaceTenantContext $tenant): void
    {
        if ($actor->accountInternalId !== $tenant->accountInternalId || $actor->sessionInternalId !== $tenant->sessionInternalId) {
            throw new \DomainException('Clip tenant context does not match the authenticated session.');
        }
    }

    private function requirePermission(
        AuthenticatedAccountContext $actor,
        AccountWorkspaceTenantContext $tenant,
        string $code,
    ): void {
        $this->authorization->requireAllowed(new AuthorizationRequest(
            AuthorizationSubject::fromAuthenticatedContext($actor),
            new PermissionCode($code),
            new WorkspaceAuthorizationScope($tenant),
        ));
    }
}
