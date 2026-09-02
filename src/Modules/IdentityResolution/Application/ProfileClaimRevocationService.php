<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Application;

use Qmdb\Modules\IdentityAccess\Domain\IdempotencyClaimStatus;
use Qmdb\Modules\IdentityAccess\Domain\Repository\IdentityAccessRepository;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityResolution\Infrastructure\Persistence\MySqlIdentityResolutionRepository;
use Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\People\Application\PeopleProfileSecurityNotificationService;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventSubjectKind;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequest;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequirementGuard;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationSubject;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\PlatformAuthorizationScope;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Database\Transaction\TransactionOptions;
use Qmdb\Shared\Database\Transaction\TransactionRetryPolicy;
use Qmdb\Shared\Time\Clock;

final readonly class ProfileClaimRevocationService
{
    public function __construct(private MySqlIdentityResolutionRepository $repository, private IdentityAccessRepository $idempotency, private IdentityFingerprintGenerator $fingerprints, private AuthorizationRequirementGuard $authorization, private StepUpGuard $stepUp, private SecurityAuditEventAppender $audit, private PeopleProfileSecurityNotificationService $notifications, private TransactionManager $transactions, private Clock $clock)
    {
    }

    public function revokeByGuardian(AuthenticatedAccountContext $actor, ProfileClaimRevocationCommand $command): void
    {
        $this->revoke($actor, $command, false);
    }

    public function revokeByPlatform(AuthenticatedAccountContext $actor, ProfileClaimRevocationCommand $command): void
    {
        $this->authorization->requireAllowed(new AuthorizationRequest(AuthorizationSubject::fromAuthenticatedContext($actor), new PermissionCode('platform.people_profile_claims.authorize'), new PlatformAuthorizationScope()));
        $this->revoke($actor, $command, true);
    }

    private function revoke(AuthenticatedAccountContext $actor, ProfileClaimRevocationCommand $command, bool $platform): void
    {
        $this->transactions->transactional(function () use ($actor, $command, $platform): void {
            $now = $this->clock->now();
            $idempotency = $this->idempotency->claimIdempotency($command->submission, 'PROFILE_CLAIM_REVOKE', $this->fingerprints->generate('profile-claim-revoke-idempotency', $actor->accountInternalId . "\0" . $command->claimPublicId . "\0" . $command->expectedVersion), $now);
            if ($idempotency === IdempotencyClaimStatus::CONFLICT) {
                throw new \DomainException('Profile claim revocation conflicts with a prior request.');
            }
            $claim = $this->repository->claimByPublicId($command->claimPublicId, true);
            if ($claim === null) {
                throw new \DomainException('Profile claim is unavailable.');
            }
            if ($idempotency === IdempotencyClaimStatus::REPLAY && $claim['status'] === 'REVOKED') {
                return;
            }
            if ((int) $claim['version'] !== $command->expectedVersion) {
                throw new \DomainException('Profile claim is unavailable.');
            }
            if (!$platform && ($claim['authorization_type'] !== 'GUARDIAN' || (int) $claim['authorized_by_account_id'] !== $actor->accountInternalId || $this->repository->guardianAuthority($actor->accountInternalId, (int) $claim['person_id'], true) === null)) {
                throw new \DomainException('Profile claim is unavailable.');
            }
            $this->stepUp->consumeWithGrant($actor, StepUpAction::PROFILE_CLAIM_REVOKE);
            $this->repository->transitionClaim($claim, 'REVOKED', $actor->accountInternalId, $platform ? 'PLATFORM_PROFILE_CLAIM_REVOKED' : 'GUARDIAN_PROFILE_CLAIM_REVOKED', $now);
            $this->audit->platform(SecurityEventCode::PROFILE_CLAIM_REVOKED, SecurityEventSubjectKind::PROFILE_CLAIM, (string) $claim['public_id'], $actor->accountId->toString(), $now, ['claim_public_id' => $claim['public_id']], 'PROFILE_CLAIM_REVOKED');
            $this->notifications->create((int) $claim['claimant_account_id'], \Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType::PROFILE_CLAIM_REVOKED, (string) $claim['public_id'], $now);
            $this->idempotency->completeIdempotency($command->submission, $now);
        }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }
}
