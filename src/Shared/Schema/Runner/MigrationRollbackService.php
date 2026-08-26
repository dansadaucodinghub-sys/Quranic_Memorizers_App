<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Runner;

use Qmdb\Shared\Configuration\ApplicationEnvironment;
use Qmdb\Shared\Schema\Connection\SchemaConnectionProvider;
use Qmdb\Shared\Schema\Exception\SchemaException;
use Qmdb\Shared\Schema\Lock\SchemaMutationLock;
use Qmdb\Shared\Schema\Migration\MigrationChecksum;
use Qmdb\Shared\Schema\Migration\MigrationEventType;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationRegistry;
use Qmdb\Shared\Schema\Migration\MigrationStatus;
use Qmdb\Shared\Schema\State\SchemaStateRepository;
use Throwable;

final readonly class MigrationRollbackService
{
    public function __construct(
        private ApplicationEnvironment $environment,
        private SchemaConnectionProvider $provider,
        private SchemaMutationLock $lockManager,
        private MigrationRegistry $registry,
        private MigrationChecksum $checksum,
        private SchemaStateRepository $repository,
    ) {
    }

    public function rollback(string $migrationId, string $confirmation): SchemaRunSummary
    {
        if ($migrationId !== $confirmation) {
            throw new SchemaException('ROLLBACK_CONFIRMATION_MISMATCH', 'Rollback confirmation does not match.');
        }
        if (!in_array($this->environment, [ApplicationEnvironment::LOCAL, ApplicationEnvironment::TEST], true)) {
            throw new SchemaException('ROLLBACK_ENVIRONMENT_PROHIBITED', 'Rollback is prohibited in this environment.');
        }
        $id = new MigrationId($migrationId);
        $migration = $this->registry->find($id);
        if ($migration === null) {
            throw new SchemaException('ROLLBACK_UNKNOWN_MIGRATION', 'Migration is not registered.');
        }

        $lock = $this->lockManager->acquire();
        try {
            $records = $this->repository->migrationRecords();
            $record = $records[$migrationId] ?? null;
            if ($record?->status !== MigrationStatus::APPLIED || !$migration->reversible()) {
                throw new SchemaException('ROLLBACK_NOT_ELIGIBLE', 'Migration is not eligible for rollback.');
            }
            if (!hash_equals($record->checksum, $this->checksum->migrationBinary($migration))) {
                throw new SchemaException('ROLLBACK_DRIFT', 'Drift prevents rollback.');
            }
            foreach ($migration->up() as $step) {
                $stored = $record->appliedStepChecksums[$step->id()->value()] ?? null;
                if ($stored === null || !hash_equals($stored, $this->checksum->stepBinary($step))) {
                    throw new SchemaException('ROLLBACK_STEP_DRIFT', 'Applied step drift prevents rollback.');
                }
            }
            foreach ($records as $candidate) {
                if (
                    in_array(
                        $candidate->status,
                        [MigrationStatus::FAILED, MigrationStatus::ROLLBACK_FAILED],
                        true,
                    )
                ) {
                    throw new SchemaException(
                        'ROLLBACK_UNRESOLVED_FAILURE',
                        'Unresolved migration failure prevents rollback.',
                    );
                }
            }
            $latestApplied = null;
            foreach ($this->registry->ordered() as $candidate) {
                if (($records[$candidate->id()->value()] ?? null)?->status === MigrationStatus::APPLIED) {
                    $latestApplied = $candidate->id()->value();
                }
            }
            if ($latestApplied !== $migrationId || $this->hasAppliedDependent($migrationId, $records)) {
                throw new SchemaException(
                    'ROLLBACK_NOT_LATEST',
                    'Only the latest independent migration can roll back.',
                );
            }

            $batch = $record->batch;
            $this->repository->markMigration($migration, MigrationStatus::ROLLING_BACK, $batch);
            $this->repository->migrationEvent($migration, MigrationEventType::ROLLBACK_STARTED, $batch);
            $up = $migration->up();
            try {
                foreach ($migration->down() as $index => $step) {
                    $statement = $this->provider->connection()->prepare($step->sql());
                    $statement->execute($step->parameters());
                    $appliedStep = $up[count($up) - 1 - $index] ?? null;
                    if ($appliedStep !== null) {
                        $this->repository->removeMigrationStep($migration, $appliedStep);
                    }
                    $this->repository->migrationEvent(
                        $migration,
                        MigrationEventType::STEP_ROLLED_BACK,
                        $batch,
                        $step->id()->value(),
                    );
                }
                $this->repository->markMigration($migration, MigrationStatus::ROLLED_BACK, $batch);
                $this->repository->migrationEvent($migration, MigrationEventType::ROLLED_BACK, $batch);
            } catch (Throwable $exception) {
                $this->repository->markMigration(
                    $migration,
                    MigrationStatus::ROLLBACK_FAILED,
                    $batch,
                    'ROLLBACK_STEP_FAILED',
                );
                $this->repository->migrationEvent(
                    $migration,
                    MigrationEventType::ROLLBACK_FAILED,
                    $batch,
                    null,
                    'ROLLBACK_STEP_FAILED',
                );
                throw new SchemaException('ROLLBACK_STEP_FAILED', 'Migration rollback failed.', $exception);
            }

            return new SchemaRunSummary([$migrationId], false);
        } finally {
            $this->lockManager->release($lock);
        }
    }

    /** @param array<string, \Qmdb\Shared\Schema\Migration\MigrationRecord> $records */
    private function hasAppliedDependent(string $migrationId, array $records): bool
    {
        foreach ($this->registry->ordered() as $candidate) {
            if (($records[$candidate->id()->value()] ?? null)?->status !== MigrationStatus::APPLIED) {
                continue;
            }
            foreach ($candidate->dependencies() as $dependency) {
                if ($dependency->value() === $migrationId) {
                    return true;
                }
            }
        }

        return false;
    }
}
