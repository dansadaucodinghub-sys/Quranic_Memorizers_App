<?php

declare(strict_types=1);

namespace Qmdb\Shared\Database\Result;

/** Validates untyped database-result values at the infrastructure boundary. */
final class DatabaseResult
{
    /** @return array<string, mixed>|null */
    public static function nullableRow(mixed $value, string $context): ?array
    {
        if ($value === false || $value === null) {
            return null;
        }
        if (!is_array($value)) {
            throw new \UnexpectedValueException($context . ' row is invalid.');
        }

        return self::row($value, $context);
    }

    /** @return array<string, mixed> */
    public static function row(mixed $value, string $context): array
    {
        if (!is_array($value)) {
            throw new \UnexpectedValueException($context . ' row is invalid.');
        }
        $row = [];
        foreach ($value as $column => $field) {
            if (!is_string($column)) {
                throw new \UnexpectedValueException($context . ' row is invalid.');
            }
            $row[$column] = $field;
        }

        return $row;
    }

    /** @return list<array<string, mixed>> */
    public static function rows(mixed $rows, string $context): array
    {
        if (!is_array($rows)) {
            throw new \UnexpectedValueException($context . ' rows are invalid.');
        }
        $result = [];
        foreach ($rows as $row) {
            $result[] = self::row($row, $context);
        }

        return $result;
    }

    public static function string(mixed $value, string $context): string
    {
        if (!is_string($value)) {
            throw new \UnexpectedValueException($context . ' value is invalid.');
        }

        return $value;
    }

    public static function nullableString(mixed $value, string $context): ?string
    {
        return $value === null ? null : self::string($value, $context);
    }

    public static function integer(mixed $value, string $context): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value) || !preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $value)) {
            throw new \UnexpectedValueException($context . ' value is invalid.');
        }

        return (int) $value;
    }
}
