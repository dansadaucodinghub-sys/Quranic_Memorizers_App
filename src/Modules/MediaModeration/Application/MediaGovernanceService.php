<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaModeration\Application;

use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\{IdentityRateLimiter, IdentityRateLimitAttempt, IdentityRateLimitScope, IdentityRateLimitPolicy};
use Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\MediaModeration\Domain\MediaGovernanceAction;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\SecurityAudit\Domain\{SecurityEventCode, SecurityEventSubjectKind};
use Qmdb\Modules\SecurityAuthorization\Application\{AuthorizationRequirementGuard, AuthorizationRequest, AuthorizationSubject};
use Qmdb\Modules\SecurityAuthorization\Domain\{PermissionCode, WorkspaceAuthorizationScope};
use Qmdb\Modules\TenancyContext\Domain\AccountWorkspaceTenantContext;
use Qmdb\Shared\Database\Transaction\{TransactionManager, TransactionOptions, TransactionRetryPolicy};
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Time\Clock;

final readonly class MediaGovernanceService
{
    public function __construct(
        private MediaGovernanceRepository $repository,
        private AuthorizationRequirementGuard $authorization,
        private StepUpGuard $stepUp,
        private IdentityRateLimiter $rateLimits,
        private IdentityFingerprintGenerator $fingerprints,
        private SecurityAuditEventAppender $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {
    }

    /** @return array{asset_id:string,status:string,version:int} */
    public function execute(AuthenticatedAccountContext $actor, AccountWorkspaceTenantContext $tenant, UuidV7 $submission, UuidV7 $assetId, int $version, MediaGovernanceAction $action, string $reason, string $holdCode = 'GOVERNANCE', ?\Qmdb\Modules\MediaModeration\Domain\MediaConsentEvidence $evidence = null): array
    {
        if ($tenant->accountInternalId !== $actor->accountInternalId || $tenant->sessionInternalId !== $actor->sessionInternalId) {
            throw new \DomainException('Media context does not match the authenticated session.');
        }
        if ($version < 1 || preg_match('/\A[A-Z][A-Z0-9_]{1,47}\z/', $reason) !== 1 || !in_array($holdCode, ['TECHNICAL', 'SECURITY', 'CONSENT', 'RIGHTS', 'GOVERNANCE'], true)) {
            throw new \InvalidArgumentException('Invalid media governance request.');
        }
        if (($action === MediaGovernanceAction::GRANT_CONSENT) !== ($evidence !== null)) {
            throw new \InvalidArgumentException('Consent evidence is required only for the grant action.');
        }
        $this->authorization->requireAllowed(new AuthorizationRequest(AuthorizationSubject::fromAuthenticatedContext($actor), new PermissionCode($action->permission()), new WorkspaceAuthorizationScope($tenant)));
        $attempt = new IdentityRateLimitAttempt(IdentityRateLimitScope::MEDIA_GOVERNANCE_ACCOUNT, $this->fingerprints->generate('media-governance-account', (string) $actor->accountInternalId), new IdentityRateLimitPolicy(60, 20, 60));
        $limit = $this->rateLimits->consume([$attempt], $this->clock->now());
        if (!$limit->allowed) {
            throw new MediaGovernanceRateLimited($limit->retryAfterSeconds);
        }
        $fingerprint = $this->fingerprints->generate('media-governance', implode("\0", [$actor->accountInternalId, $tenant->workspaceInternalId, $assetId->toString(), $version, $action->value, $reason, $holdCode, $evidence?->fingerprint() ?? '']))->toBinary();
        return $this->transactions->transactional(function () use ($actor, $tenant, $submission, $assetId, $version, $action, $reason, $holdCode, $fingerprint, $evidence): array {
            // The asset lock serializes approval, consent withdrawal, holds and replay lookup.
            $asset = $this->repository->find($tenant->workspaceInternalId, $assetId, true);
            if ($asset === null) {
                throw new \DomainException('Media is unavailable.');
            }
            $replay = $this->repository->replay($submission, $fingerprint);
            if ($replay !== null) {
                return $replay;
            }
            if ($asset->version !== $version) {
                throw new \DomainException('Media changed. Reload before trying again.');
            }
            $target = $asset->target($action, $actor->accountInternalId);
            if ($action->stepUp() !== null) {
                $this->stepUp->consumeWithGrant($actor, $action->stepUp());
            }
            $now = $this->clock->now();
            if ($evidence !== null) {
                $this->repository->grantConsent($asset, $evidence, $actor->accountInternalId, $now);
            }
            $this->repository->apply($asset, $action, $target, $actor->accountInternalId, $holdCode, $now);
            $this->repository->record($submission, $fingerprint, $asset, $action, $target, $actor->accountInternalId, $reason, $now);
            $this->audit->workspace(SecurityEventCode::MEDIA_GOVERNANCE_CHANGED, $tenant->workspacePublicId(), SecurityEventSubjectKind::MEDIA_ASSET, $asset->publicId, $actor->accountId->toString(), $now, ['previous_status' => $asset->status, 'new_status' => $target, 'reason_code' => $reason, 'operation_public_id' => $submission->toString()], $reason);
            return ['asset_id' => $asset->publicId, 'status' => $target, 'version' => $asset->version + 1];
        }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }
}
