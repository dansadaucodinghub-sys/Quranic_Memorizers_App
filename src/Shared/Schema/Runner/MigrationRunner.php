<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Runner;

use PDO;
use Qmdb\Shared\Schema\Connection\SchemaConnectionProvider;
use Qmdb\Shared\Schema\Exception\SchemaException;
use Qmdb\Shared\Schema\Lock\SchemaMutationLock;
use Qmdb\Shared\Schema\Metadata\SchemaMetadataInstallation;
use Qmdb\Shared\Schema\Migration\MigrationChecksum;
use Qmdb\Shared\Schema\Migration\MigrationEventType;
use Qmdb\Shared\Schema\Migration\MigrationPlanStatus;
use Qmdb\Shared\Schema\Migration\MigrationPlanner;
use Qmdb\Shared\Schema\Migration\MigrationRegistry;
use Qmdb\Shared\Schema\Migration\MigrationStatus;
use Qmdb\Shared\Schema\State\SchemaStateRepository;
use Throwable;

final readonly class MigrationRunner
{
    public function __construct(
        private SchemaConnectionProvider $provider,
        private SchemaMutationLock $lockManager,
        private SchemaMetadataInstallation $installer,
        private MigrationRegistry $registry,
        private MigrationPlanner $planner,
        private MigrationChecksum $checksum,
        private SchemaStateRepository $repository,
    ) {
    }

    public function run(): SchemaRunSummary
    {
        $lock = $this->lockManager->acquire();
        try {
            $this->installer->installWithinLock();
            $records = $this->repository->migrationRecords();
            $plan = $this->planner->plan($this->registry, $records);
            if ($plan->isBlocked()) {
                $this->recordDriftEvents($plan, $records);
                throw new SchemaException('MIGRATION_PLAN_BLOCKED', 'Migration execution is blocked by schema state.');
            }
            $pending = $plan->pending();
            if ($pending === []) {
                return new SchemaRunSummary([], true);
            }

            $batch = $this->repository->nextMigrationBatch();
            $processed = [];
            foreach ($pending as $item) {
                $migration = $this->registry->find(new \Qmdb\Shared\Schema\Migration\MigrationId($item->id));
                if ($migration === null) {
                    throw new SchemaException('MIGRATION_NOT_REGISTERED', 'Migration is not registered.');
                }
                $existingRecord = array_key_exists($item->id, $records) ? $records[$item->id] : null;
                $existingSteps = $existingRecord === null ? [] : $existingRecord->appliedStepChecksums;
                $this->applyMigration($migration, $existingSteps, $batch);
                $processed[] = $item->id;
            }

            return new SchemaRunSummary($processed, false);
        } finally {
            $this->lockManager->release($lock);
        }
    }

    /** @param array<string, string> $existingSteps */
    private function applyMigration(
        \Qmdb\Shared\Schema\Migration\Migration $migration,
        array $existingSteps,
        int $batch,
    ): void {
        $started = hrtime(true);
        $this->repository->startMigration($migration, $this->checksum->migrationBinary($migration), $batch);
        $this->repository->migrationEvent($migration, MigrationEventType::STARTED, $batch);
        try {
            foreach ($migration->up() as $step) {
                if (isset($existingSteps[$step->id()->value()])) {
                    continue;
                }
                $stepStarted = hrtime(true);
                $statement = $this->provider->connection()->prepare($step->sql());
                $statement->execute($step->parameters());
                $milliseconds = self::elapsedMilliseconds($stepStarted);
                $this->repository->recordMigrationStep(
                    $migration,
                    $step,
                    $this->checksum->stepBinary($step),
                    $milliseconds,
                );
                $this->repository->migrationEvent(
                    $migration,
                    MigrationEventType::STEP_APPLIED,
                    $batch,
                    $step->id()->value(),
                    null,
                    $milliseconds,
                );
                $existingSteps[$step->id()->value()] = $this->checksum->stepBinary($step);
            }
            $milliseconds = self::elapsedMilliseconds($started);
            $this->repository->markMigration($migration, MigrationStatus::APPLIED, $batch, null, $milliseconds);
            $this->repository->migrationEvent(
                $migration,
                MigrationEventType::APPLIED,
                $batch,
                null,
                null,
                $milliseconds,
            );
        } catch (Throwable $exception) {
            $status = $existingSteps === [] ? MigrationStatus::FAILED : MigrationStatus::PARTIAL;
            $event = $status === MigrationStatus::PARTIAL
                ? MigrationEventType::PARTIAL
                : MigrationEventType::FAILED;
            $this->repository->markMigration($migration, $status, $batch, 'MIGRATION_STEP_FAILED');
            $this->repository->migrationEvent(
                $migration,
                $event,
                $batch,
                null,
                'MIGRATION_STEP_FAILED',
            );
            throw new SchemaException('MIGRATION_STEP_FAILED', 'Migration step failed.', $exception);
        }
    }

    /** @param array<string, \Qmdb\Shared\Schema\Migration\MigrationRecord> $records */
    private function recordDriftEvents(
        \Qmdb\Shared\Schema\Migration\MigrationPlan $plan,
        array $records,
    ): void {
        foreach ($plan->items as $item) {
            if ($item->status !== MigrationPlanStatus::DRIFTED) {
                continue;
            }
            $migration = $this->registry->find(new \Qmdb\Shared\Schema\Migration\MigrationId($item->id));
            if ($migration === null) {
                continue;
            }
            $batch = array_key_exists($item->id, $records) ? $records[$item->id]->batch : 0;
            $this->repository->markMigration($migration, MigrationStatus::DRIFTED, $batch, 'MIGRATION_DRIFT');
            $this->repository->migrationEvent(
                $migration,
                MigrationEventType::DRIFT_DETECTED,
                $batch,
                null,
                'MIGRATION_DRIFT',
            );
        }
    }

    private static function elapsedMilliseconds(int $started): int
    {
        return max(0, (int) round((hrtime(true) - $started) / 1_000_000));
    }
}
