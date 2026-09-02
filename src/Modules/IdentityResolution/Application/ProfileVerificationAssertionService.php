<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Application;

use Qmdb\Modules\IdentityAccess\Domain\IdempotencyClaimStatus;
use Qmdb\Modules\IdentityAccess\Domain\Repository\IdentityAccessRepository;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityResolution\Configuration\IdentityResolutionConfiguration;
use Qmdb\Modules\IdentityResolution\Infrastructure\Persistence\MySqlIdentityResolutionRepository;
use Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\People\Application\PeopleProfileSecurityNotificationService;
use Qmdb\Modules\People\Application\PersonCanonicalIdentityResolver;
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

final readonly class ProfileVerificationAssertionService
{
    public function __construct(private MySqlIdentityResolutionRepository $repository, private PersonCanonicalIdentityResolver $canonical, private IdentityAccessRepository $idempotency, private IdentityFingerprintGenerator $fingerprints, private AuthorizationRequirementGuard $authorization, private StepUpGuard $stepUp, private PeopleProfileSecurityNotificationService $notifications, private SecurityAuditEventAppender $audit, private IdentityResolutionConfiguration $configuration, private TransactionManager $transactions, private Clock $clock)
    {
    }

    public function record(AuthenticatedAccountContext $actor, ProfileVerificationAssertionCommand $command): string
    {
        if ($command->reference === '' || strlen($command->reference) > $this->configuration->reviewReferenceMaximumBytes || $command->justification === '' || strlen($command->justification) > $this->configuration->reviewJustificationMaximumBytes) {
            throw new \InvalidArgumentException('Profile record-review evidence is invalid.');
        }
        $this->authorization->requireAllowed(new AuthorizationRequest(AuthorizationSubject::fromAuthenticatedContext($actor), new PermissionCode('platform.people_profile_verifications.manage'), new PlatformAuthorizationScope()));

        return $this->transactions->transactional(function () use ($actor, $command): string {
            $now = $this->clock->now();
            $person = preg_match('/\\AQMP-[A-Z2-9]{16}\\z/', strtoupper($command->personReference)) === 1 ? $this->repository->personByRegistryCode($command->personReference, true) : $this->repository->personByPublicId($command->personReference, true);
            if ($person === null) {
                throw new \DomainException('Profile record-status assertion is unavailable.');
            }
            $person = $this->repository->personById($this->canonical->resolve((int) $person['id']), true);
            if ($person === null || $person['status'] !== 'ACTIVE' || ($self = $this->repository->activeSelfLinkForAccount($actor->accountInternalId, true)) !== null && (int) $self['person_id'] === (int) $person['id'] || $this->repository->guardianAuthority($actor->accountInternalId, (int) $person['id'], true) !== null) {
                throw new \DomainException('Profile record-status assertion is unavailable.');
            }
            $idempotency = $this->idempotency->claimIdempotency($command->submission, 'PROFILE_VERIFICATION_RECORD', $this->fingerprints->generate('profile-verification-record-idempotency', $actor->accountInternalId . "\0" . $person['public_id'] . "\0" . hash('sha256', $command->reference . "\0" . $command->justification)), $now);
            if ($idempotency === IdempotencyClaimStatus::CONFLICT) {
                throw new \DomainException('Profile record-status assertion is unavailable.');
            }
            if ($idempotency === IdempotencyClaimStatus::REPLAY) {
                $assertion = $this->repository->reviewerAssertionByEvidence(
                    (int) $person['id'],
                    $actor->accountInternalId,
                    $command->reference,
                    $command->justification,
                    true,
                );
                if ($assertion === null) {
                    throw new \DomainException('Profile record-status assertion replay is unavailable.');
                }

                return (string) $assertion['public_id'];
            }
            $this->stepUp->consumeWithGrant($actor, StepUpAction::PROFILE_VERIFICATION_RECORD);
            $assertion = $this->repository->recordReviewerAssertion((int) $person['id'], $actor->accountInternalId, $command->reference, $command->justification, $now);
            $this->audit->platform(SecurityEventCode::PROFILE_VERIFICATION_RECORDED, SecurityEventSubjectKind::PROFILE_VERIFICATION, $assertion, $actor->accountId->toString(), $now, ['person_public_id' => $person['public_id'], 'assertion_type' => 'QMDB_RECORD_REVIEWED'], 'PROFILE_VERIFICATION_RECORDED');
            $this->notifications->create($actor->accountInternalId, \Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType::PROFILE_VERIFICATION_RECORDED, $assertion, $now);
            $this->idempotency->completeIdempotency($command->submission, $now);

            return $assertion;
        }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }
}
