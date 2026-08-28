<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use UnexpectedValueException;

final readonly class AuthorizationPersistence
{
    public static function format(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }

    /** @return array<string, mixed>|null */
    public static function row(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }
        $row = [];
        foreach ($value as $column => $field) {
            if (!is_string($column)) {
                throw new UnexpectedValueException('Authorization persistence row is invalid.');
            }
            $row[$column] = $field;
        }

        return $row;
    }

    /** @param array<string, mixed> $row */
    public static function string(array $row, string $column): string
    {
        $value = $row[$column] ?? null;
        if (!is_string($value)) {
            throw new UnexpectedValueException('Authorization persistence row has an invalid shape.');
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    public static function nullableString(array $row, string $column): ?string
    {
        $value = $row[$column] ?? null;
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw new UnexpectedValueException('Authorization persistence row has an invalid shape.');
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    public static function integer(array $row, string $column): int
    {
        $value = $row[$column] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value) || preg_match('/\A[0-9]+\z/D', $value) !== 1) {
            throw new UnexpectedValueException('Authorization persistence row has an invalid shape.');
        }

        return (int) $value;
    }

    /** @param array<string, mixed> $row */
    public static function nullableInteger(array $row, string $column): ?int
    {
        return ($row[$column] ?? null) === null ? null : self::integer($row, $column);
    }
}
