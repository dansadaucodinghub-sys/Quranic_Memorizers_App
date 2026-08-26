<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Schema;

use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Schema\Checksum\CanonicalChecksum;
use Qmdb\Shared\Schema\Health\SchemaHealthCheck;
use Qmdb\Shared\Schema\Health\SchemaHealthStatus;
use Qmdb\Shared\Schema\Metadata\SchemaMetadataInspection;
use Qmdb\Shared\Schema\Metadata\SchemaMetadataReport;
use Qmdb\Shared\Schema\Metadata\SchemaMetadataStatus;
use Qmdb\Shared\Schema\Migration\MigrationChecksum;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationPlanner;
use Qmdb\Shared\Schema\Migration\MigrationRecord;
use Qmdb\Shared\Schema\Migration\MigrationRegistry;
use Qmdb\Shared\Schema\Migration\MigrationStatus;
use Qmdb\Shared\Schema\State\SchemaStateReader;
use Qmdb\Tests\Support\Schema\TestMigration;

final class SchemaHealthCheckTest extends TestCase
{
    public function testMissingMetadataIsNotReadyWithoutReadingLedger(): void
    {
        $reader = new class implements SchemaStateReader {
            public function migrationRecords(): array
            {
                throw new \LogicException('Missing metadata must stop before ledger access.');
            }

            public function seedRecords(): array
            {
                return [];
            }
        };
        $report = $this->health(SchemaMetadataStatus::NOT_INSTALLED, $reader, new MigrationRegistry([]))->check();

        self::assertSame(SchemaHealthStatus::NOT_INSTALLED, $report->status);
        self::assertSame('not_ready', $report->publicStatus());
    }

    public function testCleanEmptyRegistryIsReady(): void
    {
        $report = $this->health(
            SchemaMetadataStatus::READY,
            $this->reader([]),
            new MigrationRegistry([]),
        )->check();

        self::assertSame(SchemaHealthStatus::READY, $report->status);
        self::assertSame('ready', $report->publicStatus());
    }

    public function testPendingMigrationIsNotReady(): void
    {
        $migration = new TestMigration(new MigrationId('20260825010101_pending'), 'Pending migration.');
        $report = $this->health(
            SchemaMetadataStatus::READY,
            $this->reader([]),
            new MigrationRegistry([$migration]),
        )->check();

        self::assertSame(SchemaHealthStatus::PENDING_MIGRATIONS, $report->status);
    }

    public function testRollbackFailureHasSpecificInternalStatus(): void
    {
        $record = new MigrationRecord(
            '20260825010101_failed_rollback',
            str_repeat('x', 32),
            MigrationStatus::ROLLBACK_FAILED,
            1,
        );
        $report = $this->health(
            SchemaMetadataStatus::READY,
            $this->reader([$record->id => $record]),
            new MigrationRegistry([]),
        )->check();

        self::assertSame(SchemaHealthStatus::ROLLBACK_FAILED, $report->status);
    }

    private function health(
        SchemaMetadataStatus $status,
        SchemaStateReader $reader,
        MigrationRegistry $registry,
    ): SchemaHealthCheck {
        $inspection = new class ($status) implements SchemaMetadataInspection {
            public function __construct(private readonly SchemaMetadataStatus $status)
            {
            }

            public function verify(): SchemaMetadataReport
            {
                return new SchemaMetadataReport($this->status, 'TEST_STATUS');
            }
        };
        $checksum = new MigrationChecksum(new CanonicalChecksum());

        return new SchemaHealthCheck($inspection, $reader, $registry, new MigrationPlanner($checksum));
    }

    /** @param array<string, MigrationRecord> $records */
    private function reader(array $records): SchemaStateReader
    {
        return new class ($records) implements SchemaStateReader {
            /** @param array<string, MigrationRecord> $records */
            public function __construct(private readonly array $records)
            {
            }

            public function migrationRecords(): array
            {
                return $this->records;
            }

            public function seedRecords(): array
            {
                return [];
            }
        };
    }
}
