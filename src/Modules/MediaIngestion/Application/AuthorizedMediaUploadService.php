<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaIngestion\Application;

use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitAttempt;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitPolicy;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitScope;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\MediaCatalog\Application\MediaEvidenceRepository;
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
use Qmdb\Shared\Database\Transaction\TransactionOptions;
use Qmdb\Shared\Database\Transaction\TransactionRetryPolicy;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Time\Clock;

/** The only P9 browser/API upload mutation boundary. */
final readonly class AuthorizedMediaUploadService
{
    public function __construct(private MediaUploadWorkflow $workflow, private MediaEvidenceRepository $assets, private AuthorizationRequirementGuard $authorization, private IdentityRateLimiter $rateLimits, private IdentityFingerprintGenerator $fingerprints, private SecurityAuditEventAppender $audit, private TransactionManager $transactions, private Clock $clock)
    {
    }

    /** @return array{asset_id:string,status:string,version:int,replayed:bool} */
    public function upload(AuthenticatedAccountContext $actor, AccountWorkspaceTenantContext $tenant, UuidV7 $submission, string $purpose, string $kind, string $filename, string $contents): array
    {
        if ($actor->accountInternalId !== $tenant->accountInternalId || $actor->sessionInternalId !== $tenant->sessionInternalId) {
            throw new \DomainException('Media tenant context does not match the authenticated session.');
        }
        $this->authorization->requireAllowed(new AuthorizationRequest(AuthorizationSubject::fromAuthenticatedContext($actor), new PermissionCode('media.assets.upload'), new WorkspaceAuthorizationScope($tenant)));
        $attempt = new IdentityRateLimitAttempt(IdentityRateLimitScope::MEDIA_UPLOAD_ACCOUNT, $this->fingerprints->generate('p9-media-upload-account', (string)$actor->accountInternalId), new IdentityRateLimitPolicy(60, 8, 60));
        if (!$this->rateLimits->consume([$attempt], $this->clock->now())->allowed) {
            throw new \DomainException('Media upload is temporarily unavailable.');
        }
        if ($contents === '') {
            throw new \InvalidArgumentException('Media upload is empty.');
        }
        return $this->transactions->transactional(function () use ($actor, $tenant, $submission, $purpose, $kind, $filename, $contents): array {
            $fingerprint = $this->fingerprints->generate('p9-media-upload', implode("\0", [$tenant->workspaceInternalId,$actor->accountInternalId,$purpose,$kind,$filename,hash('sha256', $contents)]))->toBinary();
            $receipt = $this->assets->claimUploadSubmission($tenant->workspaceInternalId, $actor->accountInternalId, $submission, $fingerprint, $this->clock->now());
            if ($receipt['state'] === 'CONFLICT') {
                throw new \DomainException('Media idempotency submission conflicts with a different request.');
            }
            if ($receipt['state'] === 'REPLAY') {
                if ($receipt['asset_id'] === null || $receipt['status'] === null || $receipt['version'] === null) {
                    throw new \UnexpectedValueException('Media replay receipt is incomplete.');
                }
                return ['asset_id' => $receipt['asset_id'],'status' => $receipt['status'],'version' => $receipt['version'],'replayed' => true];
            }
            $now = $this->clock->now();
            $asset = $this->workflow->initiate($tenant->workspaceInternalId, $actor->accountInternalId, $purpose, $kind, $filename, 'quarantine/' . $submission->toString() . '.bin', $now);
            $result = $this->workflow->finalize($tenant->workspaceInternalId, $actor->accountInternalId, UuidV7::fromString($asset['public_id']), $asset['version'], $contents, $now);
            $this->assets->ensurePrivateDeliveryPolicy($tenant->workspaceInternalId, $asset['id'], $now);
            $this->assets->enqueueScan($tenant->workspaceInternalId, $asset['id'], $now);
            $this->assets->completeUploadSubmission($submission, $result['asset_id'], $result['status'], $result['version'], $now);
            $this->audit->workspace(SecurityEventCode::MEDIA_ASSET_UPLOADED, $tenant->workspacePublicId(), SecurityEventSubjectKind::MEDIA_ASSET, $result['asset_id'], $actor->accountId->toString(), $now, ['new_status' => $result['status'],'operation_public_id' => $submission->toString()]);
            return ['asset_id' => $result['asset_id'],'status' => $result['status'],'version' => $result['version'],'replayed' => false];
        }, TransactionOptions::readWrite(retryPolicy:new TransactionRetryPolicy(3, 15, 150)));
    }
}
