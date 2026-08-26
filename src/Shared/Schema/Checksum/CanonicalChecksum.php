<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Checksum;

final readonly class CanonicalChecksum
{
    /** @param array<array-key, mixed> $payload */
    public function binary(array $payload): string
    {
        return hash('sha256', $this->encode($payload), true);
    }

    /** @param array<array-key, mixed> $payload */
    public function hexadecimal(array $payload): string
    {
        return hash('sha256', $this->encode($payload));
    }

    public function normalizeSql(string $sql): string
    {
        return str_replace(["\r\n", "\r"], "\n", $sql);
    }

    /** @param array<array-key, mixed> $payload */
    private function encode(array $payload): string
    {
        return json_encode(
            $this->canonicalize($payload),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
        );
    }

    private function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }
}
