<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\State;

use PDO;
use PDOStatement;
use RuntimeException;

final readonly class PdoResultReader
{
    /** @return list<array<string, mixed>> */
    public static function queryRows(PDO $connection, string $sql): array
    {
        $statement = $connection->query($sql);
        if (!$statement instanceof PDOStatement) {
            throw new RuntimeException('Database query did not return a statement.');
        }

        return self::rows($statement);
    }

    /** @return list<array<string, mixed>> */
    public static function rows(PDOStatement $statement): array
    {
        $rawRows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $rows = [];
        foreach ($rawRows as $rawRow) {
            if (!is_array($rawRow)) {
                throw new RuntimeException('Database row is invalid.');
            }
            $row = [];
            foreach ($rawRow as $key => $value) {
                if (!is_string($key)) {
                    throw new RuntimeException('Database column name is invalid.');
                }
                $row[$key] = $value;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /** @param array<string, mixed> $row */
    public static function string(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value)) {
            throw new RuntimeException('Database column type is invalid.');
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    public static function integer(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value) || preg_match('/\A[0-9]+\z/', $value) !== 1) {
            throw new RuntimeException('Database numeric column type is invalid.');
        }

        return (int) $value;
    }

    public static function scalarInteger(PDO $connection, string $sql): int
    {
        $statement = $connection->query($sql);
        if (!$statement instanceof PDOStatement) {
            throw new RuntimeException('Database query did not return a statement.');
        }
        $value = $statement->fetchColumn();
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value) || preg_match('/\A[0-9]+\z/', $value) !== 1) {
            throw new RuntimeException('Database scalar type is invalid.');
        }

        return (int) $value;
    }
}
