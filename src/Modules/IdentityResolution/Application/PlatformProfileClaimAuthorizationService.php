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
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequest;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequirementGuard;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationSubject;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\PlatformAuthorizationScope;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Database\Transaction\TransactionOptions;
use Qmdb\Shared\Database\Transaction\TransactionRetryPolicy;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Time\Clock;

final readonly class PlatformProfileClaimAuthorizationService
{
    public function __construct(
        private MySqlIdentityResolutionRepository $repository,
        private ProfileClaimPairingResolver $pairings,
        private IdentityAccessRepository $idempotency,
        private IdentityRateLimiter $rateLimiter,
        private IdentityFingerprintGenerator $fingerprints,
        private AuthorizationRequirementGuard $authorization,
        private StepUpGuard $stepUp,
        private PeopleProfilesConfiguration $people,
        private IdentityResolutionConfiguration $configuration,
        private SecurityAuditEventAppender $audit,
        private PeopleProfileSecurityNotificationService $notifications,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {
    }

    public function authorize(AuthenticatedAccountContext $actor, PlatformProfileClaimAuthorizationCommand $command, string $peerFingerprint = 'private'): ProfileClaimAuthorizationResult
    {
        $this->authorizePlatform($actor, 'platform.people_profile_claims.authorize');
        $this->validateReview($command);
        if (
            !$this->rateLimiter->consume([
            new IdentityRateLimitAttempt(IdentityRateLimitScope::PROFILE_CLAIM_AUTHORIZATION_ACCOUNT, $this->fingerprints->generate('profile-claim-platform-account', (string) $actor->accountInternalId), $this->policy()),
            new IdentityRateLimitAttempt(IdentityRateLimitScope::PROFILE_CLAIM_AUTHORIZATION_PEER, $this->fingerprints->generate('profile-claim-platform-peer', $peerFingerprint), $this->policy()),
            ], $this->clock->now())->allowed
        ) {
            throw new \DomainException('Profile claim authorization is temporarily unavailable.');
        }

        return $this->transactions->transactional(function () use ($actor, $command): ProfileClaimAuthorizationResult {
            $now = $this->clock->now();
            $idempotency = $this->idempotency->claimIdempotency($command->submission, 'PROFILE_CLAIM_AUTHORIZE_PLATFORM', $this->fingerprints->generate('profile-claim-platform-idempotency', $actor->accountInternalId . "\0" . hash('sha256', strtoupper($command->personRegistryCode) . "\0" . $command->reviewReference . "\0" . $command->reviewJustification)), $now);
            if ($idempotency === IdempotencyClaimStatus::CONFLICT) {
                throw new \DomainException('Profile claim authorization conflicts with a prior request.');
            }
            $person = $this->repository->personByRegistryCode($command->personRegistryCode, true);
            if ($person === null || $person['status'] !== 'ACTIVE' || !ProfileClaimEligibility::adult((string) $person['birth_date'], $this->people->minorThresholdYears, $now)) {
                throw new \DomainException('Profile claim authorization is unavailable.');
            }
            if ($idempotency === IdempotencyClaimStatus::REPLAY) {
                $claim = $this->repository->platformClaimByReview(
                    (int) $person['id'],
                    $actor->accountInternalId,
                    $command->reviewReference,
                    $command->reviewJustification,
                    true,
                );
                if ($claim === null) {
                    throw new \DomainException('Profile claim authorization replay is unavailable.');
                }

                return new ProfileClaimAuthorizationResult((string) $claim['public_id'], true);
            }
            $this->stepUp->consumeWithGrant($actor, StepUpAction::PROFILE_CLAIM_AUTHORIZE_PLATFORM);
            $pairing = $this->pairings->resolveForAuthorization($command->pairingCode, $now);
            if ((int) $pairing['account_id'] === $actor->accountInternalId || $this->repository->activeSelfLinkForPerson((int) $person['id'], true) !== null || $this->repository->activeAccount((int) $pairing['account_id'], true) === null || $this->repository->activeSelfLinkForAccount((int) $pairing['account_id'], true) !== null || $this->repository->pendingClaimForAccount((int) $pairing['account_id'], true) !== null) {
                throw new \DomainException('Profile claim authorization is unavailable.');
            }
            $claim = $this->repository->createClaim(UuidV7::generate()->toString(), $pairing, (int) $person['id'], $actor->accountInternalId, 'PLATFORM_RECORD_REVIEW', null, $command->reviewReference, $command->reviewJustification, $this->configuration, $now);
            $this->audit->platform(SecurityEventCode::PROFILE_CLAIM_AUTHORIZED, SecurityEventSubjectKind::PROFILE_CLAIM, (string) $claim['public_id'], $actor->accountId->toString(), $now, ['claim_public_id' => $claim['public_id'], 'person_public_id' => $person['public_id'], 'authorization_type' => 'PLATFORM_RECORD_REVIEW'], 'PROFILE_CLAIM_AUTHORIZED');
            $this->notifications->create((int) $pairing['account_id'], \Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType::PROFILE_CLAIM_AUTHORIZED, (string) $claim['public_id'], $now);
            $this->idempotency->completeIdempotency($command->submission, $now);

            return new ProfileClaimAuthorizationResult((string) $claim['public_id'], false);
        }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }

    private function authorizePlatform(AuthenticatedAccountContext $actor, string $permission): void
    {
        $this->authorization->requireAllowed(new AuthorizationRequest(AuthorizationSubject::fromAuthenticatedContext($actor), new PermissionCode($permission), new PlatformAuthorizationScope()));
    }

    private function validateReview(PlatformProfileClaimAuthorizationCommand $command): void
    {
        if ($command->reviewReference === '' || strlen($command->reviewReference) > $this->configuration->reviewReferenceMaximumBytes || $command->reviewJustification === '' || strlen($command->reviewJustification) > $this->configuration->reviewJustificationMaximumBytes) {
            throw new \InvalidArgumentException('Profile record-review evidence is invalid.');
        }
    }

    private function policy(): IdentityRateLimitPolicy
    {
        return new IdentityRateLimitPolicy($this->configuration->claimWindowSeconds, $this->configuration->claimMaximumAttempts, $this->configuration->claimWindowSeconds);
    }
}
