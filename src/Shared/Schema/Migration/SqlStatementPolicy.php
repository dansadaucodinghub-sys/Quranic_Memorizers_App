<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Migration;

use InvalidArgumentException;

final readonly class SqlStatementPolicy
{
    /**
     * @param array<string, mixed> $parameters
     * @return array<string, bool|float|int|string|null>
     */
    public static function validateParameters(array $parameters): array
    {
        foreach ($parameters as $name => $value) {
            if (preg_match('/\A:[a-z][a-z0-9_]*\z/', $name) !== 1) {
                throw new InvalidArgumentException('SQL parameter name is invalid.');
            }
            if (!is_bool($value) && !is_float($value) && !is_int($value) && !is_string($value) && $value !== null) {
                throw new InvalidArgumentException('SQL parameters must be scalar or null.');
            }
        }

        return $parameters;
    }

    public static function assertDescription(string $description): void
    {
        if (trim($description) === '' || strlen($description) > 200 || preg_match('/[\x00-\x1F\x7F]/', $description)) {
            throw new InvalidArgumentException('SQL step description is invalid.');
        }
    }

    public static function assertMigrationSql(string $sql): void
    {
        self::assertSingleStatement($sql);
        self::assertNotProhibited($sql);
        if (preg_match('/\A\s*(?:CREATE|ALTER|DROP|RENAME|TRUNCATE|INSERT|UPDATE|DELETE)\b/i', $sql) !== 1) {
            throw new InvalidArgumentException('Migration SQL operation is not allowed.');
        }
    }

    public static function assertSeedSql(string $sql): void
    {
        self::assertSingleStatement($sql);
        self::assertNotProhibited($sql);
        if (preg_match('/\A\s*(?:INSERT|UPDATE|DELETE)\b/i', $sql) !== 1) {
            throw new InvalidArgumentException('Seed SQL must be DML.');
        }
    }

    private static function assertSingleStatement(string $sql): void
    {
        $trimmed = trim($sql);
        if ($trimmed === '') {
            throw new InvalidArgumentException('SQL statement cannot be empty.');
        }
        $withoutTerminator = rtrim($trimmed, "; \t\n\r\0\x0B");
        if (str_contains($withoutTerminator, ';')) {
            throw new InvalidArgumentException('Multiple SQL statements are prohibited.');
        }
    }

    private static function assertNotProhibited(string $sql): void
    {
        $prohibited = '/\b(?:SET\s+GLOBAL|SET\s+FOREIGN_KEY_CHECKS|GRANT|REVOKE|CREATE\s+USER|ALTER\s+USER|'
            . 'DROP\s+USER|LOAD\s+DATA|SOURCE|DELIMITER|USE|START\s+TRANSACTION|BEGIN|COMMIT|ROLLBACK)\b/i';
        if (preg_match($prohibited, $sql) === 1) {
            throw new InvalidArgumentException('SQL statement contains a prohibited operation.');
        }
    }
}
