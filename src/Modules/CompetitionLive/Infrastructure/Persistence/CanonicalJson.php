<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionLive\Infrastructure\Persistence;

/** Canonical JSON used for checksums only; it refuses ambiguous values. */
final class CanonicalJson
{
    /** @param array<array-key, mixed> $value */
    public static function encode(array $value): string
    {
        return json_encode(self::normalize($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** @param array<array-key, mixed> $value
     * @return array<array-key, mixed>
     */
    private static function normalize(array $value): array
    {
        if (array_is_list($value)) {
            return array_map(static fn (mixed $item): mixed => self::scalarOrArray($item), $value);
        }
        $normalized = [];
        foreach ($value as $key => $item) {
            if (!is_string($key) || $key === '' || preg_match('/[\x00-\x1F]/', $key) === 1) {
                throw new \InvalidArgumentException('Live-event payload has an invalid key.');
            }
            $normalized[$key] = self::scalarOrArray($item);
        }
        ksort($normalized, SORT_STRING);

        return $normalized;
    }

    private static function scalarOrArray(mixed $value): mixed
    {
        if (is_array($value)) {
            return self::normalize($value);
        }
        if (is_string($value) && (str_contains($value, "\0") || !preg_match('//u', $value))) {
            throw new \InvalidArgumentException('Live-event payload has invalid UTF-8.');
        }
        if (is_float($value) || is_object($value) || is_resource($value)) {
            throw new \InvalidArgumentException('Live-event payload contains an unsupported value.');
        }

        return $value;
    }
}
