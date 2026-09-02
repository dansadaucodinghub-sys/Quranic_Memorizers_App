<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Application;

use Qmdb\Modules\IdentityAccess\Domain\IdempotencyClaimStatus;
use Qmdb\Modules\IdentityAccess\Domain\Repository\IdentityAccessRepository;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitAttempt;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitPolicy;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitScope;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Modules\IdentityResolution\Configuration\IdentityResolutionConfiguration;
use Qmdb\Modules\IdentityResolution\Infrastructure\Persistence\MySqlIdentityResolutionRepository;
use Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\People\Application\PeopleProfileSecurityNotificationService;
use Qmdb\Modules\People\Configuration\PeopleProfilesConfiguration;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventSubjectKind;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Database\Transaction\TransactionOptions;
use Qmdb\Shared\Database\Transaction\TransactionRetryPolicy;
use Qmdb\Shared\Time\Clock;

final readonly class ProfileClaimAcceptanceService
{
    public function __construct(
        private MySqlIdentityResolutionRepository $repository,
        private IdentityAccessRepository $idempotency,
        private IdentityRateLimiter $rateLimiter,
        private IdentityFingerprintGenerator $fingerprints,
        private StepUpGuard $stepUp,
        private PeopleProfilesConfiguration $people,
        private IdentityResolutionConfiguration $configuration,
        private SecurityAuditEventAppender $audit,
        private PeopleProfileSecurityNotificationService $notifications,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {
    }

    public function accept(AuthenticatedAccountContext $actor, ProfileClaimAcceptanceCommand $command, string $peerFingerprint = 'private'): ProfileClaimAcceptanceResult
    {
        if (
            !$this->rateLimiter->consume([
            new IdentityRateLimitAttempt(IdentityRateLimitScope::PROFILE_CLAIM_REDEMPTION_ACCOUNT, $this->fingerprints->generate('profile-claim-accept-account', (string) $actor->accountInternalId), $this->policy()),
            new IdentityRateLimitAttempt(IdentityRateLimitScope::PROFILE_CLAIM_REDEMPTION_PEER, $this->fingerprints->generate('profile-claim-accept-peer', $peerFingerprint), $this->policy()),
            ], $this->clock->now())->allowed
        ) {
            throw new \DomainException('Profile claim acceptance is temporarily unavailable.');
        }

        return $this->transactions->transactional(function () use ($actor, $command): ProfileClaimAcceptanceResult {
            $now = $this->clock->now();
            $idempotency = $this->idempotency->claimIdempotency($command->submission, 'PROFILE_CLAIM_ACCEPT', $this->fingerprints->generate('profile-claim-accept-idempotency', $actor->accountInternalId . "\0" . $command->claimPublicId . "\0" . $command->expectedVersion), $now);
            if ($idempotency === IdempotencyClaimStatus::CONFLICT) {
                throw new \DomainException('Profile claim acceptance conflicts with a prior request.');
            }
            $claim = $this->repository->claimForAccount($command->claimPublicId, $actor->accountInternalId, true);
            if ($claim === null) {
                throw new \DomainException('Profile claim is unavailable.');
            }
            $person = $this->repository->personById((int) $claim['person_id'], true);
            if ($person === null) {
                throw new \DomainException('Profile claim is unavailable.');
            }
            if ($idempotency === IdempotencyClaimStatus::REPLAY && $claim['status'] === 'ACCEPTED') {
                return new ProfileClaimAcceptanceResult((string) $person['public_id'], true);
            }
            if ($claim['status'] !== 'PENDING_ACCEPTANCE' || (int) $claim['version'] !== $command->expectedVersion || new \DateTimeImmutable((string) $claim['expires_at']) <= $now || $person['status'] !== 'ACTIVE' || !ProfileClaimEligibility::adult((string) $person['birth_date'], $this->people->minorThresholdYears, $now) || $this->repository->activeAccount($actor->accountInternalId, true) === null || $this->repository->activeSelfLinkForAccount($actor->accountInternalId, true) !== null || $this->repository->activeSelfLinkForPerson((int) $person['id'], true) !== null) {
                throw new \DomainException('Profile claim is unavailable.');
            }
            $this->stepUp->consumeWithGrant($actor, StepUpAction::PROFILE_CLAIM_ACCEPT);
            $accepted = $this->repository->transitionClaim($claim, 'ACCEPTED', $actor->accountInternalId, 'PROFILE_CLAIM_ACCEPTED', $now);
            $this->repository->createSelfLink($actor->accountInternalId, (int) $person['id'], $now);
            $this->repository->revokeActiveGuardianshipsForDependent((int) $person['id'], $actor->accountInternalId, $now);
            $this->repository->createClaimAssertions($accepted, $actor->accountInternalId, $now);
            $this->repository->revokeOtherPendingClaims((int) $person['id'], $actor->accountInternalId, $actor->accountInternalId, $now);
            $this->audit->platform(SecurityEventCode::PROFILE_CLAIM_ACCEPTED, SecurityEventSubjectKind::PROFILE_CLAIM, (string) $claim['public_id'], $actor->accountId->toString(), $now, ['claim_public_id' => $claim['public_id'], 'person_public_id' => $person['public_id'], 'authorization_type' => $claim['authorization_type']], 'PROFILE_CLAIM_ACCEPTED');
            $this->notifications->create($actor->accountInternalId, \Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType::PROFILE_CLAIM_ACCEPTED, (string) $claim['public_id'], $now);
            $this->idempotency->completeIdempotency($command->submission, $now);

            return new ProfileClaimAcceptanceResult((string) $person['public_id'], false);
        }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }

    private function policy(): IdentityRateLimitPolicy
    {
        return new IdentityRateLimitPolicy($this->configuration->claimWindowSeconds, $this->configuration->claimMaximumAttempts, $this->configuration->claimWindowSeconds);
    }
}
