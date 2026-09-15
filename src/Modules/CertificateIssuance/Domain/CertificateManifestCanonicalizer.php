<?php

declare(strict_types=1);

namespace Qmdb\Modules\CertificateIssuance\Domain;

/**
 * Produces the exact bytes protected by a certificate signature.
 *
 * The canonical form deliberately accepts only scalar values and recursively
 * sorted maps.  This makes a manifest independently reproducible without
 * treating presentation or PDF bytes as the authority.
 */
final class CertificateManifestCanonicalizer
{
    /** @param array<string, mixed> $manifest */
    public function canonicalize(array $manifest): string
    {
        $normalized = $this->normalizeMap($manifest);

        return json_encode(
            $normalized,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /** @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    private function normalizeMap(array $value): array
    {
        $normalized = [];
        foreach ($value as $key => $item) {
            if ($key === '' || preg_match('/[\x00-\x1F]/', $key) === 1) {
                throw new \InvalidArgumentException('Certificate manifest contains an invalid key.');
            }
            $normalized[$key] = $this->normalizeValue($item);
        }
        ksort($normalized, SORT_STRING);

        return $normalized;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                return array_map(fn (mixed $item): mixed => $this->normalizeValue($item), $value);
            }

            $map = [];
            foreach ($value as $key => $item) {
                if (!is_string($key)) {
                    throw new \InvalidArgumentException('Certificate manifest object key is invalid.');
                }
                $map[$key] = $item;
            }
            return $this->normalizeMap($map);
        }
        if (is_string($value)) {
            if (str_contains($value, "\0") || preg_match('//u', $value) !== 1) {
                throw new \InvalidArgumentException('Certificate manifest contains invalid UTF-8.');
            }

            return $value;
        }
        if (is_int($value) || is_bool($value) || $value === null) {
            return $value;
        }
        if (is_float($value)) {
            throw new \InvalidArgumentException('Certificate manifest must not contain floating-point authority.');
        }

        throw new \InvalidArgumentException('Certificate manifest contains an unsupported value.');
    }
}
