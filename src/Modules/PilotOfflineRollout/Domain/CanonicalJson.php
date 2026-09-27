<?php

declare(strict_types=1);

namespace Qmdb\Modules\PilotOfflineRollout\Domain;

final readonly class CanonicalJson
{
    /** @param array<array-key, mixed> $value */
    public function encode(array $value): string
    {
        $normalized = $this->normalize($value);

        return json_encode(
            $normalized,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
        );
    }

    /**
     * @param array<array-key, mixed> $value
     * @return array<array-key, mixed>
     */
    private function normalize(array $value): array
    {
        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = array_is_list($item)
                    ? array_map(fn (mixed $entry): mixed => is_array($entry) ? $this->normalize($entry) : $entry, $item)
                    : $this->normalize($item);
            }
        }

        return $value;
    }
}
