<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Application;

use Qmdb\Modules\IdentityAccess\Domain\IdempotencyClaimStatus;
use Qmdb\Modules\IdentityAccess\Domain\Repository\IdentityAccessRepository;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityResolution\Infrastructure\Persistence\MySqlIdentityResolutionRepository;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\People\Application\PeopleProfileSecurityNotificationService;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventSubjectKind;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Database\Transaction\TransactionOptions;
use Qmdb\Shared\Database\Transaction\TransactionRetryPolicy;
use Qmdb\Shared\Time\Clock;

final readonly class ProfileClaimDeclineService
{
    public function __construct(private MySqlIdentityResolutionRepository $repository, private IdentityAccessRepository $idempotency, private IdentityFingerprintGenerator $fingerprints, private SecurityAuditEventAppender $audit, private PeopleProfileSecurityNotificationService $notifications, private TransactionManager $transactions, private Clock $clock)
    {
    }

    public function decline(AuthenticatedAccountContext $actor, ProfileClaimDeclineCommand $command): void
    {
        $this->transactions->transactional(function () use ($actor, $command): void {
            $now = $this->clock->now();
            $idempotency = $this->idempotency->claimIdempotency($command->submission, 'PROFILE_CLAIM_DECLINE', $this->fingerprints->generate('profile-claim-decline-idempotency', $actor->accountInternalId . "\0" . $command->claimPublicId . "\0" . $command->expectedVersion), $now);
            if ($idempotency === IdempotencyClaimStatus::CONFLICT) {
                throw new \DomainException('Profile claim decline conflicts with a prior request.');
            }
            $claim = $this->repository->claimForAccount($command->claimPublicId, $actor->accountInternalId, true);
            if ($claim === null) {
                throw new \DomainException('Profile claim is unavailable.');
            }
            if ($idempotency === IdempotencyClaimStatus::REPLAY && $claim['status'] === 'DECLINED') {
                return;
            }
            if ((int) $claim['version'] !== $command->expectedVersion) {
                throw new \DomainException('Profile claim is unavailable.');
            }
            $this->repository->transitionClaim($claim, 'DECLINED', $actor->accountInternalId, 'PROFILE_CLAIM_DECLINED', $now);
            $this->audit->platform(SecurityEventCode::PROFILE_CLAIM_DECLINED, SecurityEventSubjectKind::PROFILE_CLAIM, (string) $claim['public_id'], $actor->accountId->toString(), $now, ['claim_public_id' => $claim['public_id']], 'PROFILE_CLAIM_DECLINED');
            $this->notifications->create((int) $claim['authorized_by_account_id'], \Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType::PROFILE_CLAIM_DECLINED, (string) $claim['public_id'], $now);
            $this->idempotency->completeIdempotency($command->submission, $now);
        }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }
}
