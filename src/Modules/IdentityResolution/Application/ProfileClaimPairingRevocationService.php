<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Application;

use Qmdb\Modules\IdentityAccess\Domain\IdempotencyClaimStatus;
use Qmdb\Modules\IdentityAccess\Domain\Repository\IdentityAccessRepository;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityResolution\Domain\IdentityResolutionSubmissionId;
use Qmdb\Modules\IdentityResolution\Infrastructure\Persistence\MySqlIdentityResolutionRepository;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventSubjectKind;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Database\Transaction\TransactionOptions;
use Qmdb\Shared\Database\Transaction\TransactionRetryPolicy;
use Qmdb\Shared\Time\Clock;

final readonly class ProfileClaimPairingRevocationService
{
    public function __construct(private MySqlIdentityResolutionRepository $repository, private IdentityAccessRepository $idempotency, private IdentityFingerprintGenerator $fingerprints, private SecurityAuditEventAppender $audit, private TransactionManager $transactions, private Clock $clock)
    {
    }

    public function revoke(AuthenticatedAccountContext $actor, string $pairingPublicId, int $expectedVersion, IdentityResolutionSubmissionId $submission): void
    {
        $this->transactions->transactional(function () use ($actor, $pairingPublicId, $expectedVersion, $submission): void {
            $now = $this->clock->now();
            $idempotency = $this->idempotency->claimIdempotency($submission, 'PROFILE_CLAIM_PAIRING_REVOKE', $this->fingerprints->generate('profile-claim-pairing-revoke-idempotency', $actor->accountInternalId . "\0" . $pairingPublicId . "\0" . $expectedVersion), $now);
            if ($idempotency === IdempotencyClaimStatus::CONFLICT) {
                throw new \DomainException('Profile claim pairing is unavailable.');
            }
            $pairing = $this->repository->pairingForAccount($pairingPublicId, $actor->accountInternalId, true);
            if ($pairing === null) {
                throw new \DomainException('Profile claim pairing is unavailable.');
            }
            if ($idempotency === IdempotencyClaimStatus::REPLAY && $pairing['status'] === 'REVOKED') {
                return;
            }
            if ($pairing['status'] !== 'ACTIVE' || (int) $pairing['version'] !== $expectedVersion) {
                throw new \DomainException('Profile claim pairing is unavailable.');
            }
            $this->repository->revokePairing($pairing, $now);
            $this->audit->platform(SecurityEventCode::PROFILE_CLAIM_PAIRING_REVOKED, SecurityEventSubjectKind::PROFILE_CLAIM, (string) $pairing['public_id'], $actor->accountId->toString(), $now, ['pairing_public_id' => $pairing['public_id']], 'PROFILE_CLAIM_PAIRING_REVOKED');
            $this->idempotency->completeIdempotency($submission, $now);
        }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }
}
