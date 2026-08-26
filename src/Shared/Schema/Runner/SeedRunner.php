<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Runner;

use Qmdb\Shared\Schema\Connection\SchemaConnectionProvider;
use Qmdb\Shared\Schema\Exception\SchemaException;
use Qmdb\Shared\Schema\Lock\SchemaMutationLock;
use Qmdb\Shared\Schema\Metadata\SchemaMetadataInstallation;
use Qmdb\Shared\Schema\Seed\SeedChecksum;
use Qmdb\Shared\Schema\Seed\SeedEventType;
use Qmdb\Shared\Schema\Seed\SeedPlanner;
use Qmdb\Shared\Schema\Seed\SeedRegistry;
use Qmdb\Shared\Schema\Seed\SeedStatus;
use Qmdb\Shared\Schema\State\SchemaStateRepository;
use Throwable;

final readonly class SeedRunner
{
    public function __construct(
        private SchemaConnectionProvider $provider,
        private SchemaMutationLock $lockManager,
        private SchemaMetadataInstallation $installer,
        private SeedRegistry $registry,
        private SeedPlanner $planner,
        private SeedChecksum $checksum,
        private SchemaStateRepository $repository,
    ) {
    }

    public function run(): SchemaRunSummary
    {
        $lock = $this->lockManager->acquire();
        try {
            $this->installer->installWithinLock();
            $records = $this->repository->seedRecords();
            $plan = $this->planner->plan($this->registry, $records);
            if ($plan->blocked) {
                $this->recordDrift($records);
                throw new SchemaException('SEED_PLAN_BLOCKED', 'Seed execution is blocked by ledger state.');
            }
            if ($plan->pending === []) {
                return new SchemaRunSummary([], true);
            }
            $batch = $this->repository->nextSeedBatch();
            $processed = [];
            foreach ($plan->pending as $seed) {
                $this->repository->startSeed($seed, $this->checksum->binary($seed), $batch);
                $this->repository->seedEvent($seed, SeedEventType::STARTED, $batch);
                $connection = $this->provider->connection();
                $started = hrtime(true);
                try {
                    $connection->beginTransaction();
                    foreach ($seed->steps() as $step) {
                        $statement = $connection->prepare($step->sql());
                        $statement->execute($step->parameters());
                    }
                    $connection->commit();
                    $milliseconds = self::elapsedMilliseconds($started);
                    $this->repository->markSeed($seed, SeedStatus::APPLIED, $batch, null, $milliseconds);
                    $this->repository->seedEvent($seed, SeedEventType::APPLIED, $batch, null, $milliseconds);
                    $processed[] = $seed->id()->value();
                } catch (Throwable $exception) {
                    if ($connection->inTransaction()) {
                        $connection->rollBack();
                    }
                    $this->repository->markSeed($seed, SeedStatus::FAILED, $batch, 'SEED_STEP_FAILED');
                    $this->repository->seedEvent(
                        $seed,
                        SeedEventType::FAILED,
                        $batch,
                        'SEED_STEP_FAILED',
                    );
                    throw new SchemaException('SEED_STEP_FAILED', 'Seed execution failed.', $exception);
                }
            }

            return new SchemaRunSummary($processed, false);
        } finally {
            $this->lockManager->release($lock);
        }
    }

    private static function elapsedMilliseconds(int $started): int
    {
        return max(0, (int) round((hrtime(true) - $started) / 1_000_000));
    }

    /** @param array<string, \Qmdb\Shared\Schema\Seed\SeedRecord> $records */
    private function recordDrift(array $records): void
    {
        foreach ($this->registry->ordered() as $seed) {
            $record = $records[$seed->id()->value()] ?? null;
            if ($record === null || hash_equals($record->checksum, $this->checksum->binary($seed))) {
                continue;
            }
            $this->repository->markSeed($seed, SeedStatus::DRIFTED, $record->batch, 'SEED_DRIFT');
            $this->repository->seedEvent(
                $seed,
                SeedEventType::DRIFT_DETECTED,
                $record->batch,
                'SEED_DRIFT',
            );
        }
    }
}
