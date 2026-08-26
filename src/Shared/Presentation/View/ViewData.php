<?php

declare(strict_types=1);

namespace Qmdb\Shared\Presentation\View;

use RuntimeException;

final readonly class ViewData
{
    /** @var array<string, mixed> */
    private array $values;

    /** @param array<mixed, mixed> $values */
    public function __construct(array $values = [])
    {
        $validated = [];
        foreach ($values as $key => $value) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException('View data keys must be strings.');
            }
            $validated[$key] = $value;
        }
        $this->values = $validated;
    }

    public function value(string $key): mixed
    {
        if (!array_key_exists($key, $this->values)) {
            throw new RuntimeException('Required view value is missing: ' . $key);
        }

        return $this->values[$key];
    }

    public function string(string $key): string
    {
        $value = $this->value($key);
        if (!is_string($value)) {
            throw new RuntimeException('Required view value is not a string: ' . $key);
        }

        return $value;
    }

    public function boolean(string $key): bool
    {
        $value = $this->value($key);
        if (!is_bool($value)) {
            throw new RuntimeException('Required view value is not a boolean: ' . $key);
        }

        return $value;
    }

    public function integer(string $key): int
    {
        $value = $this->value($key);
        if (!is_int($value)) {
            throw new RuntimeException('Required view value is not an integer: ' . $key);
        }

        return $value;
    }

    /** @return list<mixed> */
    public function list(string $key): array
    {
        $value = $this->value($key);
        if (!is_array($value) || !array_is_list($value)) {
            throw new RuntimeException('Required view value is not a list: ' . $key);
        }

        return $value;
    }

    /** @return array<string, mixed> */
    public function array(string $key): array
    {
        $value = $this->value($key);
        if (!is_array($value)) {
            throw new RuntimeException('Required view value is not an array: ' . $key);
        }

        $validated = [];
        foreach ($value as $nestedKey => $nestedValue) {
            if (!is_string($nestedKey)) {
                throw new RuntimeException('Nested view data keys must be strings: ' . $key);
            }
            $validated[$nestedKey] = $nestedValue;
        }

        return $validated;
    }
}
