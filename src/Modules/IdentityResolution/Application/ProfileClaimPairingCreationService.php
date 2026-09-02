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
use Qmdb\Modules\IdentityResolution\Domain\ProfileClaimPairingCodeGenerator;
use Qmdb\Modules\IdentityResolution\Infrastructure\Persistence\MySqlIdentityResolutionRepository;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventSubjectKind;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Database\Transaction\TransactionOptions;
use Qmdb\Shared\Database\Transaction\TransactionRetryPolicy;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Time\Clock;

final readonly class ProfileClaimPairingCreationService
{
    public function __construct(
        private MySqlIdentityResolutionRepository $repository,
        private IdentityAccessRepository $idempotency,
        private IdentityRateLimiter $rateLimiter,
        private IdentityFingerprintGenerator $fingerprints,
        private ProfileClaimPairingCodeGenerator $codes,
        private ProfileClaimPairingHasher $hasher,
        private IdentityResolutionConfiguration $configuration,
        private SecurityAuditEventAppender $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {
    }

    public function create(AuthenticatedAccountContext $actor, ProfileClaimPairingCreationCommand $command, string $peerFingerprint = 'private'): ProfileClaimPairingCreationResult
    {
        $now = $this->clock->now();
        $attempts = [
            new IdentityRateLimitAttempt(IdentityRateLimitScope::PROFILE_CLAIM_PAIRING_ACCOUNT, $this->fingerprints->generate('profile-claim-pairing-account', (string) $actor->accountInternalId), $this->policy()),
            new IdentityRateLimitAttempt(IdentityRateLimitScope::PROFILE_CLAIM_PAIRING_PEER, $this->fingerprints->generate('profile-claim-pairing-peer', $peerFingerprint), $this->policy()),
        ];
        if (!$this->rateLimiter->consume($attempts, $now)->allowed) {
            throw new \DomainException('Profile claim pairing is temporarily unavailable.');
        }

        return $this->transactions->transactional(function () use ($actor, $command): ProfileClaimPairingCreationResult {
            $now = $this->clock->now();
            $claim = $this->idempotency->claimIdempotency(
                $command->submission,
                'PROFILE_CLAIM_PAIRING_CREATE',
                $this->fingerprints->generate('profile-claim-pairing-idempotency', (string) $actor->accountInternalId),
                $now,
            );
            if ($claim === IdempotencyClaimStatus::CONFLICT) {
                throw new \DomainException('Profile claim pairing conflicts with a prior request.');
            }
            if ($claim === IdempotencyClaimStatus::REPLAY) {
                $existing = $this->repository->activePairingForAccount($actor->accountInternalId, true);
                if ($existing === null) {
                    throw new \DomainException('Profile claim pairing replay is unavailable.');
                }

                return new ProfileClaimPairingCreationResult((string) $existing['public_id'], null, true);
            }
            $account = $this->repository->activeAccount($actor->accountInternalId, true);
            if ($account === null || $this->repository->activeSelfLinkForAccount($actor->accountInternalId, true) !== null || $this->repository->pendingClaimForAccount($actor->accountInternalId, true) !== null) {
                throw new \DomainException('Profile claim pairing is unavailable.');
            }
            $code = $this->codes->generate($this->configuration->pairingEntropyBits);
            $pairing = $this->repository->createPairing(
                UuidV7::generate()->toString(),
                $actor->accountInternalId,
                $code,
                $this->hasher->hash($code),
                $this->configuration,
                $now,
            );
            $this->audit->platform(
                SecurityEventCode::PROFILE_CLAIM_PAIRING_CREATED,
                SecurityEventSubjectKind::PROFILE_CLAIM,
                (string) $pairing['public_id'],
                $actor->accountId->toString(),
                $now,
                ['pairing_public_id' => $pairing['public_id']],
                'PROFILE_CLAIM_PAIRING_CREATED',
            );
            $this->idempotency->completeIdempotency($command->submission, $now);

            return new ProfileClaimPairingCreationResult((string) $pairing['public_id'], $code->displayOnce(), false);
        }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }

    private function policy(): IdentityRateLimitPolicy
    {
        return new IdentityRateLimitPolicy($this->configuration->claimWindowSeconds, $this->configuration->claimMaximumAttempts, $this->configuration->claimWindowSeconds);
    }
}
