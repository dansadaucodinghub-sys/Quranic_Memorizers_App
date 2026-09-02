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

final readonly class ProfileVerificationRevocationService
{
    public function __construct(private MySqlIdentityResolutionRepository $repository, private IdentityAccessRepository $idempotency, private IdentityFingerprintGenerator $fingerprints, private AuthorizationRequirementGuard $authorization, private StepUpGuard $stepUp, private PeopleProfileSecurityNotificationService $notifications, private SecurityAuditEventAppender $audit, private TransactionManager $transactions, private Clock $clock)
    {
    }

    public function revoke(AuthenticatedAccountContext $actor, ProfileVerificationRevocationCommand $command): void
    {
        $this->authorization->requireAllowed(new AuthorizationRequest(AuthorizationSubject::fromAuthenticatedContext($actor), new PermissionCode('platform.people_profile_verifications.manage'), new PlatformAuthorizationScope()));
        $this->transactions->transactional(function () use ($actor, $command): void {
            $now = $this->clock->now();
            $idempotency = $this->idempotency->claimIdempotency($command->submission, 'PROFILE_VERIFICATION_REVOKE', $this->fingerprints->generate('profile-verification-revoke-idempotency', $actor->accountInternalId . "\0" . $command->personPublicId . "\0" . $command->assertionPublicId . "\0" . $command->expectedVersion), $now);
            if ($idempotency === IdempotencyClaimStatus::CONFLICT) {
                throw new \DomainException('Profile record-status assertion is unavailable.');
            }
            $person = $this->repository->personByPublicId($command->personPublicId, true);
            if ($person === null || (($self = $this->repository->activeSelfLinkForAccount($actor->accountInternalId, true)) !== null && (int) $self['person_id'] === (int) $person['id']) || $this->repository->guardianAuthority($actor->accountInternalId, (int) $person['id'], true) !== null) {
                throw new \DomainException('Profile record-status assertion is unavailable.');
            }
            $assertion = $this->repository->reviewerAssertion($command->assertionPublicId, (int) $person['id'], true);
            if ($assertion === null) {
                throw new \DomainException('Profile record-status assertion is unavailable.');
            }
            if ($idempotency === IdempotencyClaimStatus::REPLAY && $assertion['status'] === 'REVOKED') {
                return;
            }
            if ($assertion['status'] !== 'ACTIVE' || (int) $assertion['version'] !== $command->expectedVersion) {
                throw new \DomainException('Profile record-status assertion is unavailable.');
            }
            $this->stepUp->consumeWithGrant($actor, StepUpAction::PROFILE_VERIFICATION_REVOKE);
            $this->repository->revokeReviewerAssertion($assertion, $now);
            $this->audit->platform(SecurityEventCode::PROFILE_VERIFICATION_REVOKED, SecurityEventSubjectKind::PROFILE_VERIFICATION, (string) $assertion['public_id'], $actor->accountId->toString(), $now, ['person_public_id' => $person['public_id'], 'assertion_type' => 'QMDB_RECORD_REVIEWED'], 'PROFILE_VERIFICATION_REVOKED');
            $this->notifications->create($actor->accountInternalId, \Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType::PROFILE_VERIFICATION_REVOKED, (string) $assertion['public_id'], $now);
            $this->idempotency->completeIdempotency($command->submission, $now);
        }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }
}
