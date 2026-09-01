<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Domain;

use InvalidArgumentException;

final class PersonName
{
    /** @var array<string, string|null> */
    private array $value;

    /** @param array<string, mixed> $value */
    public function __construct(array $value, int $maximumBytes)
    {
        $normalized = [];
        foreach ($value as $key => $part) {
            if ($part === null) {
                $normalized[$key] = null;
                continue;
            }
            if (!is_string($part)) {
                throw new InvalidArgumentException('Person name has an invalid shape.');
            }
            $normalized[$key] = self::normalize($part, $maximumBytes);
        }
        if (($normalized['display_name'] ?? null) === null || $normalized['display_name'] === '') {
            throw new InvalidArgumentException('Person display name is required.');
        }
        $this->value = $normalized;
    }

    public function displayName(): string
    {
        $displayName = $this->value['display_name'] ?? null;
        if ($displayName === null) {
            throw new \LogicException('Person display name is unavailable.');
        }

        return $displayName;
    }

    public function givenName(): ?string
    {
        return $this->stringOrNull('given_name');
    }

    public function middleNames(): ?string
    {
        return $this->stringOrNull('middle_names');
    }

    public function familyName(): ?string
    {
        return $this->stringOrNull('family_name');
    }

    public function searchName(int $maximumBytes): string
    {
        $search = mb_strtolower($this->displayName(), 'UTF-8');
        if (strlen($search) > $maximumBytes) {
            throw new InvalidArgumentException('Person search name exceeds the configured maximum.');
        }

        return $search;
    }

    private static function normalize(string $value, int $maximumBytes): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value));
        if (
            !is_string($value) || !mb_check_encoding($value, 'UTF-8') || strlen($value) > $maximumBytes
            || str_contains($value, "\0") || preg_match('/[\x00-\x1F\x7F]/u', $value) === 1
            || str_contains($value, '<') || str_contains($value, '>')
        ) {
            throw new InvalidArgumentException('Person name is invalid.');
        }

        return $value;
    }

    private function stringOrNull(string $key): ?string
    {
        $value = $this->value[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
