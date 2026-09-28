<?php

declare(strict_types=1);

namespace Qmdb\Modules\PilotOfflineRollout\Application;

use Qmdb\Modules\PilotOfflineRollout\Infrastructure\Persistence\MySqlPilotOfflineRolloutRepository;

final readonly class P13MaintenanceService
{
    /** @var array<string,string> */
    public const array HANDLERS = [
        'pilot:readiness' => 'reconcilePilotReadiness',
        'pilot:health' => 'verifyPilotHealth',
        'rollout:plans' => 'verifyRolloutPlans',
        'rollout:waves' => 'processApprovedRolloutWaves',
        'rollout:health' => 'captureRolloutHealth',
        'devices:verify' => 'verifyDevices',
        'devices:reconcile' => 'reconcileDeviceKeys',
        'packages:prepare' => 'processPreparedPackages',
        'packages:verify' => 'verifyPackages',
        'packages:expire' => 'expirePackages',
        'packages:reconcile' => 'reconcilePackages',
        'sync:verify' => 'verifySyncSessions',
        'sync:reconcile' => 'reconcileSyncSessions',
        'conflicts:verify' => 'verifyConflicts',
        'conflicts:notify' => 'notifyUnresolvedConflicts',
        'receipts:verify' => 'verifyReceipts',
        'receipts:reconcile' => 'reconcileReceipts',
    ];

    public function __construct(private MySqlPilotOfflineRolloutRepository $repository)
    {
    }

    /** @return array{examined:int,changed:int} */
    public function run(string $operation, bool $dryRun = false): array
    {
        return match ($operation) {
            'packages:expire' => $this->repository->expirePackages($dryRun),
            'pilot:readiness' => $this->repository->reconcilePilotReadiness($dryRun),
            'pilot:health' => $this->repository->verifyPilotHealth(),
            'rollout:plans' => $this->repository->verifyRolloutPlans(),
            'rollout:waves' => $this->repository->processApprovedRolloutWaves($dryRun),
            'rollout:health' => $this->repository->captureRolloutHealth($dryRun),
            'devices:verify' => $this->repository->verifyDevices(),
            'devices:reconcile' => $this->repository->reconcileDeviceKeys($dryRun),
            'packages:prepare' => $this->repository->processPreparedPackages($dryRun),
            'packages:verify' => $this->repository->verifyPackages(),
            'packages:reconcile' => $this->repository->reconcilePackages($dryRun),
            'sync:verify' => $this->repository->verifySyncSessions(),
            'sync:reconcile' => $this->repository->reconcileSyncSessions($dryRun),
            'conflicts:verify' => $this->repository->verifyConflicts(),
            'conflicts:notify' => $this->repository->notifyUnresolvedConflicts($dryRun),
            'receipts:verify' => $this->repository->verifyReceipts(),
            'receipts:reconcile' => $this->repository->reconcileReceipts($dryRun),
            default => throw new \RuntimeException('Unknown P13 maintenance operation.'),
        };
    }

    /** @return array<string,string> */
    public function handlers(): array
    {
        return self::HANDLERS;
    }
}
