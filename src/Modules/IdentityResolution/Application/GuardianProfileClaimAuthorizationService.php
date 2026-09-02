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
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Time\Clock;

final readonly class GuardianProfileClaimAuthorizationService
{
    public function __construct(
        private MySqlIdentityResolutionRepository $repository,
        private ProfileClaimPairingResolver $pairings,
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

    public function authorize(AuthenticatedAccountContext $actor, GuardianProfileClaimAuthorizationCommand $command, string $peerFingerprint = 'private'): ProfileClaimAuthorizationResult
    {
        if (
            !$this->rateLimiter->consume([
            new IdentityRateLimitAttempt(IdentityRateLimitScope::PROFILE_CLAIM_AUTHORIZATION_ACCOUNT, $this->fingerprints->generate('profile-claim-guardian-account', (string) $actor->accountInternalId), $this->policy()),
            new IdentityRateLimitAttempt(IdentityRateLimitScope::PROFILE_CLAIM_AUTHORIZATION_PEER, $this->fingerprints->generate('profile-claim-guardian-peer', $peerFingerprint), $this->policy()),
            ], $this->clock->now())->allowed
        ) {
            throw new \DomainException('Profile claim authorization is temporarily unavailable.');
        }

        return $this->transactions->transactional(function () use ($actor, $command): ProfileClaimAuthorizationResult {
            $now = $this->clock->now();
            $idempotency = $this->idempotency->claimIdempotency($command->submission, 'PROFILE_CLAIM_AUTHORIZE_GUARDIAN', $this->fingerprints->generate('profile-claim-guardian-idempotency', $actor->accountInternalId . "\0" . hash('sha256', $command->dependentPersonPublicId)), $now);
            if ($idempotency === IdempotencyClaimStatus::CONFLICT) {
                throw new \DomainException('Profile claim authorization conflicts with a prior request.');
            }
            $person = $this->repository->personByPublicId($command->dependentPersonPublicId, true);
            if ($person === null || $person['status'] !== 'ACTIVE' || !ProfileClaimEligibility::adult((string) $person['birth_date'], $this->people->minorThresholdYears, $now)) {
                throw new \DomainException('Profile claim authorization is unavailable.');
            }
            if ($idempotency === IdempotencyClaimStatus::REPLAY) {
                $claim = $this->repository->pendingClaimForPerson((int) $person['id'], true);
                if ($claim === null) {
                    throw new \DomainException('Profile claim authorization replay is unavailable.');
                }

                return new ProfileClaimAuthorizationResult((string) $claim['public_id'], true);
            }
            $authority = $this->repository->guardianAuthority($actor->accountInternalId, (int) $person['id'], true);
            if ($authority === null || $this->repository->activeSelfLinkForPerson((int) $person['id'], true) !== null) {
                throw new \DomainException('Profile claim authorization is unavailable.');
            }
            $this->stepUp->consumeWithGrant($actor, StepUpAction::PROFILE_CLAIM_AUTHORIZE_GUARDIAN);
            $pairing = $this->pairings->resolveForAuthorization($command->pairingCode, $now);
            if ((int) $pairing['account_id'] === $actor->accountInternalId || $this->repository->activeAccount((int) $pairing['account_id'], true) === null || $this->repository->activeSelfLinkForAccount((int) $pairing['account_id'], true) !== null || $this->repository->pendingClaimForAccount((int) $pairing['account_id'], true) !== null) {
                throw new \DomainException('Profile claim authorization is unavailable.');
            }
            $claim = $this->repository->createClaim(UuidV7::generate()->toString(), $pairing, (int) $person['id'], $actor->accountInternalId, 'GUARDIAN', (int) $authority['guardianship_id'], null, null, $this->configuration, $now);
            $this->audit->platform(SecurityEventCode::PROFILE_CLAIM_AUTHORIZED, SecurityEventSubjectKind::PROFILE_CLAIM, (string) $claim['public_id'], $actor->accountId->toString(), $now, ['claim_public_id' => $claim['public_id'], 'person_public_id' => $person['public_id'], 'authorization_type' => 'GUARDIAN'], 'PROFILE_CLAIM_AUTHORIZED');
            $this->notifications->create((int) $pairing['account_id'], \Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType::PROFILE_CLAIM_AUTHORIZED, (string) $claim['public_id'], $now);
            $this->idempotency->completeIdempotency($command->submission, $now);

            return new ProfileClaimAuthorizationResult((string) $claim['public_id'], false);
        }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }

    private function policy(): IdentityRateLimitPolicy
    {
        return new IdentityRateLimitPolicy($this->configuration->claimWindowSeconds, $this->configuration->claimMaximumAttempts, $this->configuration->claimWindowSeconds);
    }
}
