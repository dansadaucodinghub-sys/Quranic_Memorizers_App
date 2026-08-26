<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\MySql;

use PDO;
use Qmdb\Shared\Background\Scheduler\Migration\CreateScheduledTaskRunsMigration;
use Qmdb\Tests\Support\MySql\SchemaMySqlIntegrationTestCase;

final class SchedulerLedgerIntegrationTest extends SchemaMySqlIntegrationTestCase
{
    public function testMigrationCreatesRequiredInnoDbLedgerAndCanRollbackAndReapply(): void
    {
        $connection = $this->schemaProvider()->connection();
        $migration = new CreateScheduledTaskRunsMigration();
        $ledgerInitiallyExisted = $this->tableExists($connection);
        $this->dropLedger($connection);

        try {
            $this->apply($connection, $migration->up());
            $table = $connection->query(
                "SELECT ENGINE FROM information_schema.TABLES "
                . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'qmdb_scheduled_task_runs'",
            );
            self::assertNotFalse($table);
            self::assertSame('InnoDB', $table->fetchColumn());

            $indexStatement = $connection->query(
                "SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns_list "
                . "FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() "
                . "AND TABLE_NAME = 'qmdb_scheduled_task_runs' GROUP BY INDEX_NAME ORDER BY INDEX_NAME",
            );
            self::assertNotFalse($indexStatement);
            $indexes = [];
            foreach ($indexStatement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (!is_array($row)) {
                    self::fail('MySQL index metadata row is invalid.');
                }
                $indexName = $row['INDEX_NAME'] ?? null;
                $columns = $row['columns_list'] ?? null;
                if (!is_string($indexName) || !is_string($columns)) {
                    self::fail('MySQL index metadata columns are invalid.');
                }
                $indexes[$indexName] = $columns;
            }
            self::assertSame('task_id,scheduled_for', $indexes['PRIMARY'] ?? null);
            self::assertSame('execution_id', $indexes['uq_qmdb_scheduled_task_runs_execution_id'] ?? null);
            self::assertArrayHasKey('ix_qmdb_scheduled_task_runs_status_scheduled', $indexes);
            self::assertArrayHasKey('ix_qmdb_scheduled_task_runs_status_lease', $indexes);

            $this->apply($connection, $migration->down());
            self::assertFalse($this->tableExists($connection));
            $this->apply($connection, $migration->up());
            self::assertTrue($this->tableExists($connection));
        } finally {
            $this->dropLedger($connection);
            if ($ledgerInitiallyExisted) {
                $this->apply($connection, $migration->up());
            }
        }

        self::assertSame($ledgerInitiallyExisted, $this->tableExists($connection));
    }

    /** @param list<\Qmdb\Shared\Schema\Migration\SqlMigrationStep> $steps */
    private function apply(PDO $connection, array $steps): void
    {
        foreach ($steps as $step) {
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
        self::assertNotFalse($statement);

        return (int) $statement->fetchColumn() === 1;
    }

    private function dropLedger(PDO $connection): void
    {
        $connection->exec('DROP TABLE IF EXISTS qmdb_scheduled_task_runs');
    }
}
