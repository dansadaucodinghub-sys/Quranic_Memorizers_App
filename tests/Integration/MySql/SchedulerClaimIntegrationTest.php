<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\MySql;

use DateTimeImmutable;
use PDO;
use Qmdb\Shared\Background\Scheduler\Infrastructure\MySqlScheduledTaskRunRepository;
use Qmdb\Shared\Background\Scheduler\Migration\CreateScheduledTaskRunsMigration;
use Qmdb\Shared\Background\Scheduler\ScheduledExecutionSlot;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskClaimDisposition;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskId;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskRunStatus;
use Qmdb\Shared\Background\Scheduler\SchedulerExecutionId;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Database\Transaction\TransactionOptions;
use Qmdb\Shared\Database\Transaction\TransactionRetryPolicy;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Transaction\MySqlRetryableTransactionFailureClassifier;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Transaction\MySqlTransactionManager;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Transaction\PdoMySqlTransactionDriver;
use Qmdb\Shared\Schema\Connection\SchemaConnectionProvider;
use Qmdb\Tests\Support\MySql\DeterministicRetryDelayStrategy;
use Qmdb\Tests\Support\MySql\RecordingSleeper;
use Qmdb\Tests\Support\MySql\SchemaMySqlIntegrationTestCase;
use RuntimeException;

final class SchedulerClaimIntegrationTest extends SchemaMySqlIntegrationTestCase
{
    public function testIndependentConnectionsEnforceClaimsLeasesOwnershipAndSafeTerminalState(): void
    {
        $firstProvider = $this->schemaProvider();
        $connection = $firstProvider->connection();
        $ledgerInitiallyExisted = $this->tableExists($connection);
        $this->createLedger($connection);
        $secondProvider = $this->schemaProvider();
        $first = $this->repository($firstProvider);
        $second = $this->repository($secondProvider);
        $now = new DateTimeImmutable('2026-08-25T09:00:00+00:00');
        $slot = new ScheduledExecutionSlot(new ScheduledTaskId('test.claim.primary'), $now);

        try {
            $claim = $first->claim($slot, new SchedulerExecutionId(str_repeat('1', 32)), $now, 30);
            self::assertSame(ScheduledTaskClaimDisposition::CLAIMED, $claim->disposition());
            self::assertSame(
                ScheduledTaskClaimDisposition::SKIPPED_ACTIVE,
                $second->claim($slot, new SchedulerExecutionId(str_repeat('2', 32)), $now, 30)->disposition(),
            );

            $reclaimed = $second->claim(
                $slot,
                new SchedulerExecutionId(str_repeat('3', 32)),
                $now->modify('+31 seconds'),
                30,
            );
            self::assertSame(ScheduledTaskClaimDisposition::RECLAIMED, $reclaimed->disposition());
            self::assertSame(2, $reclaimed->attempt());
            self::assertSame(str_repeat('3', 32), $reclaimed->executionId()->value());
            try {
                $first->markRunning($claim, $now);
                self::fail('Stale scheduler owner completed a reclaimed row.');
            } catch (RuntimeException) {
                self::addToAssertionCount(1);
            }

            $running = $second->markRunning($reclaimed, $now->modify('+31 seconds'));
            $second->markSucceeded($running, $now->modify('+32 seconds'), 1_000);
            $record = $first->find($slot);
            self::assertNotNull($record);
            self::assertSame(ScheduledTaskRunStatus::SUCCEEDED, $record->status);
            self::assertSame(
                ScheduledTaskClaimDisposition::SKIPPED_SUCCEEDED,
                $first->claim(
                    $slot,
                    new SchedulerExecutionId(str_repeat('4', 32)),
                    $now->modify('+90 seconds'),
                    30,
                )->disposition(),
            );

            $this->assertIndependentSlotsAndSafeFailure($first, $now);
        } finally {
            $connection->exec('DROP TABLE IF EXISTS qmdb_scheduled_task_runs');
            if ($ledgerInitiallyExisted) {
                $this->createLedger($connection);
            }
        }

        self::assertSame($ledgerInitiallyExisted, $this->tableExists($connection));
    }

    private function assertIndependentSlotsAndSafeFailure(
        MySqlScheduledTaskRunRepository $repository,
        DateTimeImmutable $now,
    ): void {
        $sameTime = new ScheduledExecutionSlot(new ScheduledTaskId('test.claim.other'), $now);
        $nextTime = new ScheduledExecutionSlot(new ScheduledTaskId('test.claim.primary'), $now->modify('+1 minute'));
        $sameTimeClaim = $repository->claim($sameTime, new SchedulerExecutionId(str_repeat('5', 32)), $now, 30);
        $nextTimeClaim = $repository->claim($nextTime, new SchedulerExecutionId(str_repeat('6', 32)), $now, 30);
        self::assertTrue($sameTimeClaim->isAcquired());
        self::assertTrue($nextTimeClaim->isAcquired());
        $running = $repository->markRunning($sameTimeClaim, $now);
        $repository->markFailed($running, $now->modify('+1 second'), 5, 'SAFE_TEST_FAILURE');
        $record = $repository->find($sameTime);
        self::assertNotNull($record);
        self::assertSame('SAFE_TEST_FAILURE', $record->failureCode);
        $statement = $this->schemaProvider()->connection()->prepare(
            'SELECT COUNT(*) FROM qmdb_scheduled_task_runs WHERE failure_code LIKE :secret',
        );
        $statement->execute([':secret' => '%exception%']);
        self::assertSame('0', (string) $statement->fetchColumn());
    }

    private function repository(SchemaConnectionProvider $provider): MySqlScheduledTaskRunRepository
    {
        $databaseProvider = new class ($provider) implements DatabaseConnectionProvider {
            public function __construct(private SchemaConnectionProvider $provider)
            {
            }

            public function connection(): PDO
            {
                return $this->provider->connection();
            }
        };
        $transactions = new MySqlTransactionManager(
            new PdoMySqlTransactionDriver($databaseProvider),
            new MySqlRetryableTransactionFailureClassifier(),
            new DeterministicRetryDelayStrategy(0),
            new RecordingSleeper(),
            TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 0, 0)),
        );

        return new MySqlScheduledTaskRunRepository($databaseProvider, $transactions);
    }

    private function createLedger(PDO $connection): void
    {
        $connection->exec('DROP TABLE IF EXISTS qmdb_scheduled_task_runs');
        foreach ((new CreateScheduledTaskRunsMigration())->up() as $step) {
            $statement = $connection->prepare($step->sql());
            $statement->execute($step->parameters());
        }
    }

    private function tableExists(PDO $connection): bool
    {
        $statement = $connection->query(
            "SELECT COUNT(*) FROM information_schema.TABLES "
                . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'qmdb_scheduled_task_runs'",
        );
        if ($statement === false) {
            throw new RuntimeException('Unable to inspect the scheduler ledger table.');
        }

        return (int) $statement->fetchColumn() === 1;
    }
}
