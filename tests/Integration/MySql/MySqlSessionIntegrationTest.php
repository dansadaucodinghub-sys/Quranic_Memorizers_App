<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\MySql;

use PDO;
use Qmdb\Tests\Support\MySql\MySqlIntegrationTestCase;

final class MySqlSessionIntegrationTest extends MySqlIntegrationTestCase
{
    public function testSessionAndServerInvariantsAreActive(): void
    {
        $statement = $this->provider()->connection()->query(
            'SELECT VERSION(), @@default_storage_engine, @@session.time_zone, '
            . '@@character_set_client, @@character_set_connection, @@character_set_results, '
            . '@@session.sql_mode, CURRENT_USER()',
        );
        self::assertNotFalse($statement);
        $row = $statement->fetch(PDO::FETCH_NUM);
        self::assertIsArray($row);

        self::assertStringNotContainsString('mariadb', strtolower($this->stringAt($row, 0)));
        self::assertSame('innodb', strtolower($this->stringAt($row, 1)));
        self::assertContains($this->stringAt($row, 2), ['+00:00', 'UTC']);
        self::assertSame(
            ['utf8mb4', 'utf8mb4', 'utf8mb4'],
            array_map('strtolower', [
                $this->stringAt($row, 3),
                $this->stringAt($row, 4),
                $this->stringAt($row, 5),
            ]),
        );
        $requiredModes = [
            'STRICT_TRANS_TABLES',
            'ERROR_FOR_DIVISION_BY_ZERO',
            'NO_ENGINE_SUBSTITUTION',
            'ONLY_FULL_GROUP_BY',
        ];
        foreach ($requiredModes as $mode) {
            self::assertStringContainsString($mode, $this->stringAt($row, 6));
        }
        self::assertStringStartsNotWith('root@', strtolower($this->stringAt($row, 7)));
    }

    /** @param array<array-key, mixed> $row */
    private function stringAt(array $row, int $offset): string
    {
        $value = $row[$offset] ?? null;

        return is_string($value) ? $value : '';
    }
}
