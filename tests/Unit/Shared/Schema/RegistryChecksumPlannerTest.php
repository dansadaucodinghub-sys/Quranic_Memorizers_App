<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Schema;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Schema\Checksum\CanonicalChecksum;
use Qmdb\Shared\Schema\Migration\MigrationChecksum;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationPlanStatus;
use Qmdb\Shared\Schema\Migration\MigrationPlanner;
use Qmdb\Shared\Schema\Migration\MigrationRecord;
use Qmdb\Shared\Schema\Migration\MigrationRegistry;
use Qmdb\Shared\Schema\Migration\MigrationRegistryBuilder;
use Qmdb\Shared\Schema\Migration\MigrationStatus;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;
use Qmdb\Shared\Schema\Seed\SeedChecksum;
use Qmdb\Shared\Schema\Seed\SeedId;
use Qmdb\Shared\Schema\Seed\SeedRegistry;
use Qmdb\Shared\Schema\Seed\SeedRegistryBuilder;
use Qmdb\Shared\Schema\Seed\SeedStepId;
use Qmdb\Shared\Schema\Seed\SqlSeedStep;
use Qmdb\Tests\Support\Schema\TestMigration;
use Qmdb\Tests\Support\Schema\TestSeed;

final class RegistryChecksumPlannerTest extends TestCase
{
    public function testMigrationRegistryUsesDeterministicDependencyOrder(): void
    {
        $first = $this->migration('20260825010101_first');
        $second = $this->migration('20260825010102_second', [$first->id()]);
        $registry = new MigrationRegistry([$second, $first]);

        self::assertSame([$first, $second], $registry->ordered());
    }

    public function testIndependentMigrationsUseIdOrder(): void
    {
        $first = $this->migration('20260825010101_first');
        $second = $this->migration('20260825010102_second');

        self::assertSame([$first, $second], (new MigrationRegistry([$second, $first]))->ordered());
    }

    public function testMigrationRegistryRejectsMissingDependency(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new MigrationRegistry([$this->migration('20260825010102_second', [new MigrationId('20260825010101_first')])]);
    }

    public function testMigrationRegistryRejectsCycle(): void
    {
        $firstId = new MigrationId('20260825010101_first');
        $secondId = new MigrationId('20260825010102_second');
        $this->expectException(InvalidArgumentException::class);
        new MigrationRegistry([
            $this->migration($firstId->value(), [$secondId]),
            $this->migration($secondId->value(), [$firstId]),
        ]);
    }

    public function testRegistryBuilderFreezes(): void
    {
        $builder = new MigrationRegistryBuilder();
        $builder->build();
        $this->expectException(LogicException::class);
        $builder->register($this->migration('20260825010101_first'));
    }

    public function testMigrationChecksumIsDeterministicBinaryAndHex(): void
    {
        $checksum = new MigrationChecksum(new CanonicalChecksum());
        $migration = $this->migration('20260825010101_first');

        self::assertSame($checksum->migrationBinary($migration), $checksum->migrationBinary($migration));
        self::assertSame(32, strlen($checksum->migrationBinary($migration)));
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $checksum->migrationHex($migration));
    }

    public function testLineEndingsAndParameterOrderAreCanonical(): void
    {
        $checksum = new MigrationChecksum(new CanonicalChecksum());
        $left = new SqlMigrationStep(
            new MigrationStepId('001_insert_row'),
            'Insert row.',
            "INSERT INTO qmdb_test (id, label) VALUES (:id, :label)\r\n",
            [':id' => 1, ':label' => 'x'],
        );
        $right = new SqlMigrationStep(
            new MigrationStepId('001_insert_row'),
            'Insert row.',
            "INSERT INTO qmdb_test (id, label) VALUES (:id, :label)\n",
            [':label' => 'x', ':id' => 1],
        );

        self::assertSame($checksum->stepBinary($left), $checksum->stepBinary($right));
    }

    public function testChangedSqlChangesChecksum(): void
    {
        $checksum = new MigrationChecksum(new CanonicalChecksum());
        $left = $this->migration('20260825010101_first');
        $right = new TestMigration(
            $left->id(),
            'Test migration.',
            up: [new SqlMigrationStep(
                new MigrationStepId('001_create_table'),
                'Create test table.',
                'CREATE TABLE qmdb_test_changed (id INT)',
            )],
        );

        self::assertNotSame($checksum->migrationBinary($left), $checksum->migrationBinary($right));
    }

    public function testPlannerHandlesEmptyPendingAppliedAndDrift(): void
    {
        $checksum = new MigrationChecksum(new CanonicalChecksum());
        $planner = new MigrationPlanner($checksum);
        self::assertSame([], $planner->plan(new MigrationRegistry([]), [])->items);
        $migration = $this->migration('20260825010101_first');
        $registry = new MigrationRegistry([$migration]);
        self::assertSame(MigrationPlanStatus::PENDING, $planner->plan($registry, [])->items[0]->status);
        $record = new MigrationRecord(
            $migration->id()->value(),
            $checksum->migrationBinary($migration),
            MigrationStatus::APPLIED,
            1,
        );
        self::assertSame(
            MigrationPlanStatus::APPLIED,
            $planner->plan($registry, [$record->id => $record])->items[0]->status,
        );
        $drifted = new MigrationRecord($record->id, str_repeat('x', 32), MigrationStatus::APPLIED, 1);
        self::assertSame(
            MigrationPlanStatus::DRIFTED,
            $planner->plan($registry, [$record->id => $drifted])->items[0]->status,
        );
    }

    public function testPlannerFindsOrphanedLedgerMigration(): void
    {
        $record = new MigrationRecord(
            '20260825010101_orphaned',
            str_repeat('x', 32),
            MigrationStatus::APPLIED,
            1,
        );
        $plan = (new MigrationPlanner(new MigrationChecksum(new CanonicalChecksum())))->plan(
            new MigrationRegistry([]),
            [$record->id => $record],
        );

        self::assertSame(MigrationPlanStatus::ORPHANED, $plan->items[0]->status);
        self::assertTrue($plan->isBlocked());
    }

    public function testPendingDependencyChainRemainsExecutableInTopologicalOrder(): void
    {
        $first = $this->migration('20260825010101_first');
        $second = $this->migration('20260825010102_second', [$first->id()]);
        $plan = (new MigrationPlanner(new MigrationChecksum(new CanonicalChecksum())))->plan(
            new MigrationRegistry([$second, $first]),
            [],
        );

        self::assertFalse($plan->isBlocked());
        self::assertSame([$first->id()->value(), $second->id()->value()], array_map(
            static fn ($item): string => $item->id,
            $plan->pending(),
        ));
    }

    public function testSeedRegistryOrdersDependenciesAndFreezes(): void
    {
        $first = $this->seed('20260825010101_first');
        $second = $this->seed('20260825010102_second', [$first->id()]);
        self::assertSame([$first, $second], (new SeedRegistry([$second, $first]))->ordered());

        $builder = new SeedRegistryBuilder();
        $builder->build();
        $this->expectException(LogicException::class);
        $builder->register($first);
    }

    public function testSeedChecksumIsDeterministic(): void
    {
        $checksum = new SeedChecksum(new CanonicalChecksum());
        $seed = $this->seed('20260825010101_first');
        self::assertSame($checksum->binary($seed), $checksum->binary($seed));
        self::assertSame(32, strlen($checksum->binary($seed)));
    }

    /** @param list<MigrationId> $dependencies */
    private function migration(string $id, array $dependencies = []): TestMigration
    {
        return new TestMigration(
            new MigrationId($id),
            'Test migration.',
            $dependencies,
            [new SqlMigrationStep(
                new MigrationStepId('001_create_table'),
                'Create test table.',
                'CREATE TABLE qmdb_test (id INT)',
            )],
        );
    }

    /** @param list<SeedId> $dependencies */
    private function seed(string $id, array $dependencies = []): TestSeed
    {
        return new TestSeed(
            new SeedId($id),
            'Test seed.',
            $dependencies,
            [new SqlSeedStep(
                new SeedStepId('001_insert_row'),
                'Insert row.',
                'INSERT INTO qmdb_test (id) VALUES (:id)',
                [':id' => 1],
            )],
        );
    }
}
