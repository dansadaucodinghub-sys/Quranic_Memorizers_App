<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Application;

use Qmdb\Modules\IdentityResolution\Configuration\IdentityResolutionConfiguration;
use Qmdb\Modules\IdentityResolution\Infrastructure\Persistence\MySqlIdentityResolutionRepository;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType;
use Qmdb\Modules\People\Application\PeopleProfileSecurityNotificationService;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventSubjectKind;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Database\Transaction\TransactionOptions;
use Qmdb\Shared\Database\Transaction\TransactionRetryPolicy;
use Qmdb\Shared\Time\Clock;

/** Applies bounded lifecycle expiry with an event, audit record, and notification per claim. */
final readonly class ProfileClaimMaintenanceService
{
    public function __construct(
        private MySqlIdentityResolutionRepository $repository,
        private IdentityResolutionConfiguration $configuration,
        private SecurityAuditEventAppender $audit,
        private PeopleProfileSecurityNotificationService $notifications,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {
    }

    public function maintain(): ProfileClaimMaintenanceResult
    {
        return $this->transactions->transactional(function (): ProfileClaimMaintenanceResult {
            $now = $this->clock->now();
            $expiredPairings = $this->repository->expirePairings($now, $this->configuration->maintenanceBatchSize);
            $expiredClaims = 0;
            foreach ($this->repository->expiredPendingClaims($now, $this->configuration->maintenanceBatchSize) as $claim) {
                $this->repository->transitionClaim($claim, 'EXPIRED', 0, 'PROFILE_CLAIM_EXPIRED', $now);
                $this->audit->platform(
                    SecurityEventCode::PROFILE_CLAIM_EXPIRED,
                    SecurityEventSubjectKind::PROFILE_CLAIM,
                    (string) $claim['public_id'],
                    null,
                    $now,
                    ['claim_public_id' => $claim['public_id']],
                    'PROFILE_CLAIM_EXPIRED',
                );
                $this->notifications->create(
                    (int) $claim['claimant_account_id'],
                    AccountSecurityNotificationType::PROFILE_CLAIM_EXPIRED,
                    (string) $claim['public_id'],
                    $now,
                );
                ++$expiredClaims;
            }

            return new ProfileClaimMaintenanceResult($expiredPairings, $expiredClaims);
        }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }
}
