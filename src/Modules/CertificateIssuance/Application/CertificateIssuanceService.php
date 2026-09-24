<?php

declare(strict_types=1);

namespace Qmdb\Modules\CertificateIssuance\Application;

use Qmdb\Modules\CertificateIssuance\Domain\CertificateLifecycle;
use Qmdb\Modules\CertificateIssuance\Domain\CertificateManifestCanonicalizer;
use Qmdb\Modules\CertificateIssuance\Domain\CertificatePublicIdentifierPolicy;
use Qmdb\Modules\CertificateIssuance\Domain\CertificateSigningKey;
use Qmdb\Modules\CertificateIssuance\Domain\CertificateVerificationCode;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitAttempt;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitPolicy;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitScope;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
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
use Qmdb\Shared\Database\Transaction\TransactionOptions;
use Qmdb\Shared\Database\Transaction\TransactionRetryPolicy;
use Qmdb\Shared\Configuration\ApplicationConfiguration;
use Qmdb\Shared\Configuration\ApplicationEnvironment;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Time\Clock;

/**
 * The only mutation gateway for P8 certificate issuance. It binds a FINALIZED
 * result row to an active template/key and never stores a raw verification code.
 */
final readonly class CertificateIssuanceService
{
    public function __construct(
        private CertificateIssuanceRepository $repository,
        private CertificateNumberAllocator $numbers,
        private CertificateManifestCanonicalizer $canonicalizer,
        private CertificateLifecycle $lifecycle,
        private CertificateSigningKeyProvider $keys,
        private CertificateArtifactRenderer $renderer,
        private CertificateArtifactStore $artifacts,
        private AuthorizationRequirementGuard $authorization,
        private StepUpGuard $stepUp,
        private IdentityRateLimiter $rateLimits,
        private IdentityFingerprintGenerator $fingerprints,
        private ApplicationConfiguration $configuration,
        private CertificateProductionIssuanceGate $productionGate,
        private SecurityAuditEventAppender $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {
    }

    /** @return array{certificate_id:string,certificate_number:string,verification_code:string,status:string,version:int,replayed:bool} */
    public function prepare(AuthenticatedAccountContext $actor, AccountWorkspaceTenantContext $tenant, UuidV7 $submission, UuidV7 $publicationId, UuidV7 $rowId, UuidV7 $templateId, string $type): array
    {
        if (!in_array($type, ['WINNER', 'PLACEMENT', 'PARTICIPATION', 'RECOGNITION', 'OTHER_APPROVED'], true)) {
            throw new \InvalidArgumentException('Certificate type is invalid.');
        }
        $this->productionGate->assertPreparationPermitted();
        $this->allow($actor, $tenant, 'workspace.certificates.prepare');
        $this->rate($actor);
        return $this->transactions->transactional(function () use ($actor, $tenant, $submission, $publicationId, $rowId, $templateId, $type): array {
            $fingerprint = $this->fingerprint($tenant, $actor, 'CERTIFICATE_PREPARE', $publicationId->toString() . "\0" . $rowId->toString() . "\0" . $templateId->toString() . "\0" . $type, 1);
            $completed = $this->repository->completed($submission, $fingerprint);
            if ($completed !== null) {
                return ['certificate_id' => $completed['certificate_id'],'certificate_number' => 'REPLAYED','verification_code' => '','status' => $completed['status'],'version' => $completed['version'],'replayed' => true];
            }
            $source = $this->repository->lockFinalizedResultRow($tenant->workspaceInternalId, $publicationId, $rowId);
            if ($source === null) {
                throw new \DomainException('A FINALIZED publication result row is required.');
            }
            $template = $this->repository->lockActiveTemplate($tenant->workspaceInternalId, $templateId);
            if ($template === null) {
                throw new \DomainException('An ACTIVE certificate template is required.');
            }
            $key = $this->repository->lockActiveSigningKey();
            if ($key === null) {
                throw new \DomainException('An ACTIVE certificate signing key is required.');
            }
            $verification = CertificateVerificationCode::generate();
            $now = $this->clock->now();
            $prepared = $this->repository->insertPrepared(['workspace_id' => $tenant->workspaceInternalId,'certificate_number' => $this->numbers->allocate($tenant->workspaceInternalId, (int)$now->format('Y'), 'CERTIFICATE'),'verification_hash' => $verification->hash(),'verification_fingerprint' => $verification->fingerprint(),'certificate_type' => $type,'person_id' => $source['person_id'],'publication_id' => $source['publication_id'],'result_run_id' => $source['result_run_id'],'result_row_id' => $source['result_row_id'],'template_id' => $template['id'],'signing_key_id' => $key['id'],'display_name' => $source['display_name'],'result_package_sha256' => $source['result_package_sha256'],'actor_account_id' => $actor->accountInternalId], $now);
            $this->repository->appendEvent($prepared, 'PREPARED', 'PREPARED', $actor->accountInternalId, null, $now);
            $this->repository->record($submission, $fingerprint, 'CERTIFICATE_PREPARE', $prepared, 'PREPARED', $prepared['version'], $now);
            $this->audit->workspace(SecurityEventCode::CERTIFICATE_PREPARED, $tenant->workspacePublicId(), SecurityEventSubjectKind::CERTIFICATE, $prepared['public_id'], $actor->accountId->toString(), $now, ['certificate_number' => $prepared['certificate_number'],'certificate_type' => $type,'result_publication_id' => $source['publication_public_id'],'result_row_id' => $source['result_row_public_id'],'template_code' => $template['template_code'],'signing_key_code' => $key['key_code'],'version' => 1]);
            return ['certificate_id' => $prepared['public_id'],'certificate_number' => $prepared['certificate_number'],'verification_code' => $verification->value(),'status' => 'PREPARED','version' => 1,'replayed' => false];
        }, TransactionOptions::readWrite(retryPolicy:new TransactionRetryPolicy(3, 15, 150)));
    }

    /** @return array{certificate_id:string,status:string,version:int,replayed:bool} */
    public function issue(AuthenticatedAccountContext $actor, AccountWorkspaceTenantContext $tenant, UuidV7 $submission, UuidV7 $certificateId, int $expectedVersion, string $verificationCode, string $verificationUrl): array
    {
        $this->productionGate->assertIssuancePermitted();
        $this->allow($actor, $tenant, 'workspace.certificates.issue');
        $this->rate($actor);
        $scheme = parse_url($verificationUrl, PHP_URL_SCHEME);
        if (!CertificatePublicIdentifierPolicy::isVerificationCode($verificationCode) || filter_var($verificationUrl, FILTER_VALIDATE_URL) === false || !in_array($scheme, ['http', 'https'], true) || ($this->configuration->environment() === ApplicationEnvironment::PRODUCTION && $scheme !== 'https')) {
            throw new \InvalidArgumentException('Certificate issuance verification input is invalid.');
        }
        return $this->transactions->transactional(function () use ($actor, $tenant, $submission, $certificateId, $expectedVersion, $verificationCode, $verificationUrl): array {
            $fingerprint = $this->fingerprint($tenant, $actor, 'CERTIFICATE_ISSUE', $certificateId->toString(), $expectedVersion);
            $completed = $this->repository->completed($submission, $fingerprint);
            if ($completed !== null) {
                return ['certificate_id' => $completed['certificate_id'],'status' => $completed['status'],'version' => $completed['version'],'replayed' => true];
            }
            $certificate = $this->repository->lockCertificate($tenant->workspaceInternalId, $certificateId);
            if ($certificate === null || $certificate['version'] !== $expectedVersion || !hash_equals($certificate['verification_code_hash'], hash('sha256', $verificationCode, true))) {
                throw new \DomainException('Certificate issuance is stale or unavailable.');
            }
            $this->lifecycle->assertTransition($certificate['status'], 'ISSUED');
            $this->stepUp->consumeWithGrant($actor, StepUpAction::CERTIFICATE_ISSUE);
            $key = new CertificateSigningKey($certificate['key_code'], $certificate['provider_code'], $certificate['provider_key_reference'], $certificate['public_key'], $certificate['key_status']);
            if (!$key->canIssue() || !$this->keys->verifiesPublicKey($key)) {
                throw new \DomainException('Certificate signing key is unavailable.');
            }
            $now = $this->clock->now();
            $manifest = $this->canonicalizer->canonicalize(['schema_version' => 1,'certificate_public_id' => $certificate['public_id'],'certificate_number' => $certificate['certificate_number'],'certificate_type' => $certificate['certificate_type'],'display_name' => $certificate['display_name'],'result_publication_public_id' => $certificate['result_publication_public_id'],'result_row_public_id' => $certificate['result_row_public_id'],'result_package_sha256' => bin2hex($certificate['result_package_sha256']),'template_code' => $certificate['template_code'],'template_version' => $certificate['template_version'],'signing_key_code' => $key->keyCode,'verification_code_fingerprint' => bin2hex($certificate['verification_code_fingerprint']),'issued_at' => $now->format(DATE_ATOM)]);
            $signature = $this->keys->sign($key, $manifest);
            $rendered = $this->renderer->render($verificationUrl, ['display_name' => $certificate['display_name'],'certificate_number' => $certificate['certificate_number'],'certificate_type' => $certificate['certificate_type'],'issued_at' => $now->format('Y-m-d')]);
            $pdfKey = 'certificates/' . $certificate['public_id'] . '/certificate.pdf';
            $manifestKey = 'certificates/' . $certificate['public_id'] . '/manifest.json';
            $this->artifacts->put($pdfKey, $rendered->pdf, 'application/pdf');
            $this->artifacts->put($manifestKey, $manifest, 'application/json');
            $manifestHash = hash('sha256', $manifest, true);
            if (!$this->repository->issue($certificate, $manifest, $manifestHash, $signature, $rendered->pdfSha256(), $actor->accountInternalId, $now)) {
                throw new \DomainException('Certificate changed concurrently.');
            }
            $this->repository->appendArtifacts($certificate, $pdfKey, $rendered->pdf, $manifestKey, $manifest, $now);
            $this->repository->appendEvent($certificate, 'ISSUED', 'ISSUED', $actor->accountInternalId, null, $now);
            $this->repository->record($submission, $fingerprint, 'CERTIFICATE_ISSUE', $certificate, 'ISSUED', $certificate['version'] + 1, $now);
            $this->audit->workspace(SecurityEventCode::CERTIFICATE_ISSUED, $tenant->workspacePublicId(), SecurityEventSubjectKind::CERTIFICATE, $certificate['public_id'], $actor->accountId->toString(), $now, ['certificate_number' => $certificate['certificate_number'],'certificate_type' => $certificate['certificate_type'],'manifest_sha256' => bin2hex($manifestHash),'pdf_sha256' => bin2hex($rendered->pdfSha256()),'signing_key_code' => $key->keyCode,'version' => $certificate['version'] + 1]);
            return ['certificate_id' => $certificate['public_id'],'status' => 'ISSUED','version' => $certificate['version'] + 1,'replayed' => false];
        }, TransactionOptions::readWrite(retryPolicy:new TransactionRetryPolicy(3, 15, 150)));
    }

    /** @return array{certificate_id:string,status:string,version:int,replayed:bool} */
    public function transition(AuthenticatedAccountContext $actor, AccountWorkspaceTenantContext $tenant, UuidV7 $submission, UuidV7 $certificateId, int $expectedVersion, string $operation, ?string $reason = null): array
    {
        $rule = match ($operation) {
            'CERTIFICATE_REVOKE'=>['REVOKED','REVOKED','workspace.certificates.revoke',StepUpAction::CERTIFICATE_REVOKE,SecurityEventCode::CERTIFICATE_REVOKED], 'CERTIFICATE_ARCHIVE'=>['ARCHIVED','ARCHIVED','workspace.certificates.archive',StepUpAction::CERTIFICATE_ARCHIVE,SecurityEventCode::CERTIFICATE_REVOKED], default=>throw new \InvalidArgumentException('Certificate transition operation is invalid.')
        };
        if ($rule[0] === 'REVOKED' && (preg_match('/\A[A-Z][A-Z0-9_]{1,62}\z/', $reason ?? '') !== 1)) {
            throw new \InvalidArgumentException('Certificate revocation requires a safe reason code.');
        }
        $this->allow($actor, $tenant, $rule[2]);
        $this->rate($actor);
        return $this->transactions->transactional(function () use ($actor, $tenant, $submission, $certificateId, $expectedVersion, $operation, $reason, $rule): array {
            $fingerprint = $this->fingerprint($tenant, $actor, $operation, $certificateId->toString(), $expectedVersion);
            $completed = $this->repository->completed($submission, $fingerprint);
            if ($completed !== null) {
                return ['certificate_id' => $completed['certificate_id'],'status' => $completed['status'],'version' => $completed['version'],'replayed' => true];
            }$certificate = $this->repository->lockCertificate($tenant->workspaceInternalId, $certificateId);
            if ($certificate === null || $certificate['version'] !== $expectedVersion) {
                throw new \DomainException('Certificate transition is stale or unavailable.');
            }$this->lifecycle->assertTransition($certificate['status'], $rule[0]);
            $this->stepUp->consumeWithGrant($actor, $rule[3]);
            $now = $this->clock->now();
            if (!$this->repository->transition($certificate, $rule[0], $actor->accountInternalId, $reason, $now)) {
                throw new \DomainException('Certificate changed concurrently.');
            }$this->repository->appendEvent($certificate, $rule[1], $rule[0], $actor->accountInternalId, $reason, $now);
            $this->repository->record($submission, $fingerprint, $operation, $certificate, $rule[0], $certificate['version'] + 1, $now);
            $this->audit->workspace($rule[4], $tenant->workspacePublicId(), SecurityEventSubjectKind::CERTIFICATE, $certificate['public_id'], $actor->accountId->toString(), $now, ['certificate_number' => $certificate['certificate_number'],'previous_status' => $certificate['status'],'new_status' => $rule[0],'version' => $certificate['version'] + 1], $reason);
            return ['certificate_id' => $certificate['public_id'],'status' => $rule[0],'version' => $certificate['version'] + 1,'replayed' => false];
        }, TransactionOptions::readWrite(retryPolicy:new TransactionRetryPolicy(3, 15, 150)));
    }

    private function allow(AuthenticatedAccountContext $actor, AccountWorkspaceTenantContext $tenant, string $permission): void
    {
        $this->authorization->requireAllowed(new AuthorizationRequest(AuthorizationSubject::fromAuthenticatedContext($actor), new PermissionCode($permission), new WorkspaceAuthorizationScope($tenant)));
    }
    private function rate(AuthenticatedAccountContext $actor): void
    {
        $attempt = new IdentityRateLimitAttempt(IdentityRateLimitScope::CERTIFICATE_ISSUE_ACCOUNT, $this->fingerprints->generate('certificate-issuance-account', (string)$actor->accountInternalId), new IdentityRateLimitPolicy(60, 10, 60));
        if (!$this->rateLimits->consume([$attempt], $this->clock->now())->allowed) {
            throw new \DomainException('Certificate operation is temporarily unavailable.');
        }
    }
    private function fingerprint(AccountWorkspaceTenantContext $tenant, AuthenticatedAccountContext $actor, string $operation, string $aggregate, int $version): string
    {
        return $this->fingerprints->generate('certificate-operation', implode("\0", [$tenant->workspaceInternalId,$actor->accountInternalId,$operation,$aggregate,$version]))->toBinary();
    }
}
