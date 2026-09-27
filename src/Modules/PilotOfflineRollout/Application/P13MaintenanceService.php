<?php

declare(strict_types=1);

namespace Qmdb\Modules\PilotOfflineRollout\Application;

use Qmdb\Modules\PilotOfflineRollout\Infrastructure\Persistence\MySqlPilotOfflineRolloutRepository;

final readonly class P13MaintenanceService
{
    public function __construct(private MySqlPilotOfflineRolloutRepository $repository)
    {
    }

    /** @return array{examined:int,changed:int} */
    public function run(string $operation, bool $dryRun = false): array
    {
        return match ($operation) {
            'packages:expire' => $this->repository->expirePackages($dryRun),
            'pilot:readiness', 'pilot:health', 'rollout:plans', 'rollout:waves', 'rollout:health',
            'devices:verify', 'packages:prepare', 'packages:verify', 'packages:reconcile',
            'devices:reconcile', 'sync:verify', 'sync:reconcile', 'conflicts:verify',
            'conflicts:notify', 'receipts:verify', 'receipts:reconcile' => $this->repository->verifyRuntime(),
            default => throw new \RuntimeException('Unknown P13 maintenance operation.'),
        };
    }
}
