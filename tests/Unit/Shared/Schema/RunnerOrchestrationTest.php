<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Schema;

use PDO;
use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Configuration\ApplicationEnvironment;
use Qmdb\Shared\Schema\Checksum\CanonicalChecksum;
use Qmdb\Shared\Schema\Exception\SchemaException;
use Qmdb\Shared\Schema\Migration\MigrationChecksum;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationPlanner;
use Qmdb\Shared\Schema\Migration\MigrationRecord;
use Qmdb\Shared\Schema\Migration\MigrationRegistry;
use Qmdb\Shared\Schema\Migration\MigrationStatus;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;
use Qmdb\Shared\Schema\Runner\MigrationRollbackService;
use Qmdb\Shared\Schema\Runner\MigrationRunner;
use Qmdb\Shared\Schema\Runner\SeedRunner;
use Qmdb\Shared\Schema\Seed\SeedChecksum;
use Qmdb\Shared\Schema\Seed\SeedId;
use Qmdb\Shared\Schema\Seed\SeedPlanner;
use Qmdb\Shared\Schema\Seed\SeedRegistry;
use Qmdb\Shared\Schema\Seed\SeedStatus;
use Qmdb\Shared\Schema\Seed\SeedStepId;
use Qmdb\Shared\Schema\Seed\SqlSeedStep;
use Qmdb\Shared\Schema\State\PdoResultReader;
use Qmdb\Tests\Support\Schema\InMemorySchemaStateRepository;
use Qmdb\Tests\Support\Schema\NoOpSchemaMetadataInstallation;
use Qmdb\Tests\Support\Schema\PdoSchemaConnectionProvider;
use Qmdb\Tests\Support\Schema\RecordingSchemaMutationLock;
use Qmdb\Tests\Support\Schema\TestMigration;
use Qmdb\Tests\Support\Schema\TestSeed;

final class RunnerOrchestrationTest extends TestCase
{
    public function testEmptyMigrationRegistryIsNoOpAndReleasesLock(): void
    {
        $fixture = $this->migrationFixture(new MigrationRegistry([]));
        $summary = $fixture['runner']->run();

        self::assertTrue($summary->noOp);
        self::assertSame([], $summary->processedIds);
        self::assertSame(1, $fixture['lock']->acquisitions);
        self::assertSame(1, $fixture['lock']->releases);
    }

    public function testMigrationAppliesOnceAndRecordsStepAndBatch(): void
    {
        $migration = $this->migration('20260825010101_create_runner_table');
        $fixture = $this->migrationFixture(new MigrationRegistry([$migration]));

        self::assertFalse($fixture['runner']->run()->noOp);
        self::assertTrue($fixture['runner']->run()->noOp);
        $record = $fixture['repository']->migrations[$migration->id()->value()];
        self::assertSame(MigrationStatus::APPLIED, $record->status);
        self::assertSame(1, $record->batch);
        self::assertCount(1, $record->appliedStepChecksums);
        self::assertSame(2, $fixture['lock']->releases);
    }

    public function testDependencyOrderIsExecuted(): void
    {
        $first = $this->migration('20260825010101_create_runner_table');
        $second = new TestMigration(
            new MigrationId('20260825010102_create_second_table'),
            'Create second table.',
            [$first->id()],
            [new SqlMigrationStep(
                new MigrationStepId('001_create_second'),
                'Create second table.',
                'CREATE TABLE qmdb_runner_second (id INTEGER)',
            )],
        );
        $fixture = $this->migrationFixture(new MigrationRegistry([$second, $first]));

        self::assertSame(
            [$first->id()->value(), $second->id()->value()],
            $fixture['runner']->run()->processedIds,
        );
        self::assertSame(MigrationStatus::APPLIED, $fixture['repository']->migrations[$second->id()->value()]->status);
    }

    public function testLaterStepFailureCreatesPartialStateAndReleasesLock(): void
    {
        $migration = $this->partialMigration();
        $fixture = $this->migrationFixture(new MigrationRegistry([$migration]));

        try {
            $fixture['runner']->run();
            self::fail('Failing migration unexpectedly completed.');
        } catch (SchemaException $exception) {
            self::assertSame('MIGRATION_STEP_FAILED', $exception->safeCode());
            self::assertNotNull($exception->getPrevious());
        }
        $record = $fixture['repository']->migrations[$migration->id()->value()];
        self::assertSame(MigrationStatus::PARTIAL, $record->status);
        self::assertCount(1, $record->appliedStepChecksums);
        self::assertSame(1, $fixture['lock']->releases);
    }

    public function testPartialMigrationResumesWithoutRerunningAppliedStep(): void
    {
        $migration = $this->partialMigration();
        $fixture = $this->migrationFixture(new MigrationRegistry([$migration]));
        try {
            $fixture['runner']->run();
        } catch (SchemaException) {
        }
        $fixture['pdo']->exec('CREATE TABLE qmdb_runner_dependency (id INTEGER)');

        $summary = $fixture['runner']->run();

        self::assertSame([$migration->id()->value()], $summary->processedIds);
        self::assertSame(
            MigrationStatus::APPLIED,
            $fixture['repository']->migrations[$migration->id()->value()]->status,
        );
        self::assertSame(1, PdoResultReader::scalarInteger(
            $fixture['pdo'],
            "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'qmdb_runner_partial'",
        ));
    }

    public function testMigrationDriftBlocksExecutionWithoutReplacingStoredChecksum(): void
    {
        $migration = $this->migration('20260825010101_create_runner_table');
        $fixture = $this->migrationFixture(new MigrationRegistry([$migration]));
        $stored = str_repeat('x', 32);
        $fixture['repository']->migrations[$migration->id()->value()] = new MigrationRecord(
            $migration->id()->value(),
            $stored,
            MigrationStatus::APPLIED,
            1,
        );

        try {
            $fixture['runner']->run();
            self::fail('Drifted migration unexpectedly executed.');
        } catch (SchemaException $exception) {
            self::assertSame('MIGRATION_PLAN_BLOCKED', $exception->safeCode());
        }
        self::assertSame($stored, $fixture['repository']->migrations[$migration->id()->value()]->checksum);
        self::assertSame(
            MigrationStatus::DRIFTED,
            $fixture['repository']->migrations[$migration->id()->value()]->status,
        );
        self::assertSame(1, $fixture['lock']->releases);
    }

    public function testLatestReversibleMigrationRollsBackAndCanReapply(): void
    {
        $migration = new TestMigration(
            new MigrationId('20260825010101_reversible'),
            'Reversible migration.',
            up: [new SqlMigrationStep(
                new MigrationStepId('001_create_table'),
                'Create reversible table.',
                'CREATE TABLE qmdb_runner_reversible (id INTEGER)',
            )],
            down: [new SqlMigrationStep(
                new MigrationStepId('001_drop_table'),
                'Drop reversible table.',
                'DROP TABLE qmdb_runner_reversible',
            )],
            reversible: true,
        );
        $registry = new MigrationRegistry([$migration]);
        $fixture = $this->migrationFixture($registry);
        $fixture['runner']->run();
        $rollback = new MigrationRollbackService(
            ApplicationEnvironment::TEST,
            new PdoSchemaConnectionProvider($fixture['pdo']),
            $fixture['lock'],
            $registry,
            new MigrationChecksum(new CanonicalChecksum()),
            $fixture['repository'],
        );

        $rollback->rollback($migration->id()->value(), $migration->id()->value());
        self::assertSame(
            MigrationStatus::ROLLED_BACK,
            $fixture['repository']->migrations[$migration->id()->value()]->status,
        );
        self::assertSame([], $fixture['repository']->migrations[$migration->id()->value()]->appliedStepChecksums);
        self::assertFalse($fixture['runner']->run()->noOp);
    }

    public function testSeedAppliesTransactionallyOnce(): void
    {
        $pdo = $this->pdo();
        $pdo->exec('CREATE TABLE qmdb_seed_target (id INTEGER PRIMARY KEY)');
        $seed = new TestSeed(
            new SeedId('20260825010101_insert_seed'),
            'Insert seed.',
            steps: [new SqlSeedStep(
                new SeedStepId('001_insert_row'),
                'Insert seed row.',
                'INSERT INTO qmdb_seed_target (id) VALUES (:id)',
                [':id' => 1],
            )],
        );
        $fixture = $this->seedFixture($pdo, new SeedRegistry([$seed]));

        self::assertFalse($fixture['runner']->run()->noOp);
        self::assertTrue($fixture['runner']->run()->noOp);
        self::assertSame(1, PdoResultReader::scalarInteger($pdo, 'SELECT COUNT(*) FROM qmdb_seed_target'));
        self::assertSame(SeedStatus::APPLIED, $fixture['repository']->seeds[$seed->id()->value()]->status);
    }

    public function testFailedSeedRollsBackAllDmlAndReleasesLock(): void
    {
        $pdo = $this->pdo();
        $pdo->exec('CREATE TABLE qmdb_seed_target (id INTEGER PRIMARY KEY)');
        $seed = new TestSeed(
            new SeedId('20260825010101_failing_seed'),
            'Failing seed.',
            steps: [
                new SqlSeedStep(
                    new SeedStepId('001_insert_row'),
                    'Insert seed row.',
                    'INSERT INTO qmdb_seed_target (id) VALUES (:id)',
                    [':id' => 1],
                ),
                new SqlSeedStep(
                    new SeedStepId('002_missing_table'),
                    'Insert dependent row.',
                    'INSERT INTO qmdb_seed_dependency (id) VALUES (:id)',
                    [':id' => 1],
                ),
            ],
        );
        $fixture = $this->seedFixture($pdo, new SeedRegistry([$seed]));

        try {
            $fixture['runner']->run();
            self::fail('Failing seed unexpectedly completed.');
        } catch (SchemaException $exception) {
            self::assertSame('SEED_STEP_FAILED', $exception->safeCode());
        }
        self::assertSame(0, PdoResultReader::scalarInteger($pdo, 'SELECT COUNT(*) FROM qmdb_seed_target'));
        self::assertSame(SeedStatus::FAILED, $fixture['repository']->seeds[$seed->id()->value()]->status);
        self::assertSame(1, $fixture['lock']->releases);
    }

    /**
     * @return array{
     *   runner: MigrationRunner,
     *   repository: InMemorySchemaStateRepository,
     *   lock: RecordingSchemaMutationLock,
     *   pdo: PDO
     * }
     */
    private function migrationFixture(MigrationRegistry $registry): array
    {
        $pdo = $this->pdo();
        $provider = new PdoSchemaConnectionProvider($pdo);
        $repository = new InMemorySchemaStateRepository();
        $lock = new RecordingSchemaMutationLock();
        $checksum = new MigrationChecksum(new CanonicalChecksum());

        return [
            'runner' => new MigrationRunner(
                $provider,
                $lock,
                new NoOpSchemaMetadataInstallation(),
                $registry,
                new MigrationPlanner($checksum),
                $checksum,
                $repository,
            ),
            'repository' => $repository,
            'lock' => $lock,
            'pdo' => $pdo,
        ];
    }

    /**
     * @return array{
     *   runner: SeedRunner,
     *   repository: InMemorySchemaStateRepository,
     *   lock: RecordingSchemaMutationLock
     * }
     */
    private function seedFixture(PDO $pdo, SeedRegistry $registry): array
    {
        $repository = new InMemorySchemaStateRepository();
        $lock = new RecordingSchemaMutationLock();
        $checksum = new SeedChecksum(new CanonicalChecksum());

        return [
            'runner' => new SeedRunner(
                new PdoSchemaConnectionProvider($pdo),
                $lock,
                new NoOpSchemaMetadataInstallation(),
                $registry,
                new SeedPlanner($checksum),
                $checksum,
                $repository,
            ),
            'repository' => $repository,
            'lock' => $lock,
        ];
    }

    private function migration(string $id): TestMigration
    {
        return new TestMigration(
            new MigrationId($id),
            'Create runner table.',
            up: [new SqlMigrationStep(
                new MigrationStepId('001_create_table'),
                'Create runner table.',
                'CREATE TABLE qmdb_runner_table (id INTEGER)',
            )],
        );
    }

    private function partialMigration(): TestMigration
    {
        return new TestMigration(
            new MigrationId('20260825010101_partial'),
            'Partial migration.',
            up: [
                new SqlMigrationStep(
                    new MigrationStepId('001_create_table'),
                    'Create partial table.',
                    'CREATE TABLE qmdb_runner_partial (id INTEGER)',
                ),
                new SqlMigrationStep(
                    new MigrationStepId('002_insert_dependency'),
                    'Insert dependency row.',
                    'INSERT INTO qmdb_runner_dependency (id) VALUES (:id)',
                    [':id' => 1],
                ),
            ],
        );
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        return $pdo;
    }
}
