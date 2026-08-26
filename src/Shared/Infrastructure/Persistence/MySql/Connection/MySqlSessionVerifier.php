<?php

declare(strict_types=1);

namespace Qmdb\Shared\Infrastructure\Persistence\MySql\Connection;

use PDO;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Exception\DatabaseVerificationException;
use Throwable;

final readonly class MySqlSessionVerifier
{
    /** @var list<string> */
    private const REQUIRED_SQL_MODES = [
        'STRICT_TRANS_TABLES',
        'ERROR_FOR_DIVISION_BY_ZERO',
        'NO_ENGINE_SUBSTITUTION',
        'ONLY_FULL_GROUP_BY',
    ];

    public function verify(PDO $connection): MySqlVerificationReport
    {
        try {
            $statement = $connection->query(
                'SELECT @@SESSION.time_zone AS session_time_zone, '
                . '@@character_set_client AS character_set_client, '
                . '@@character_set_connection AS character_set_connection, '
                . '@@character_set_results AS character_set_results, '
                . '@@SESSION.sql_mode AS session_sql_mode',
            );
            $row = $statement === false ? false : $statement->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $exception) {
            throw new DatabaseVerificationException('DB_VERIFY_SESSION_QUERY_FAILED', $exception);
        }
        if (!is_array($row)) {
            throw new DatabaseVerificationException('DB_VERIFY_SESSION_RESULT_INVALID');
        }
        $timezone = $this->stringField($row, 'session_time_zone');
        if (!in_array($timezone, ['+00:00', 'UTC'], true)) {
            throw new DatabaseVerificationException('DB_VERIFY_TIMEZONE_INVALID');
        }
        foreach (['character_set_client', 'character_set_connection', 'character_set_results'] as $field) {
            if (strtolower($this->stringField($row, $field)) !== 'utf8mb4') {
                throw new DatabaseVerificationException('DB_VERIFY_CHARACTER_SET_INVALID');
            }
        }
        $modes = array_map('trim', explode(',', strtoupper($this->stringField($row, 'session_sql_mode'))));
        foreach (self::REQUIRED_SQL_MODES as $mode) {
            if (!in_array($mode, $modes, true)) {
                throw new DatabaseVerificationException('DB_VERIFY_SQL_MODE_INVALID');
            }
        }

        return new MySqlVerificationReport(['timezone', 'character_sets', 'strict_sql_modes']);
    }

    /** @param array<array-key, mixed> $row */
    private function stringField(array $row, string $field): string
    {
        $value = $row[$field] ?? null;

        return is_string($value) ? $value : '';
    }
}
