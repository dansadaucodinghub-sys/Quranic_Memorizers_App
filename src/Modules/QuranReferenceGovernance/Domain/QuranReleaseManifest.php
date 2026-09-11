<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Domain;

final readonly class QuranReleaseManifest
{
    public const SCHEMA = 'qmdb.quran.release-manifest.v1';

    /**
     * @param array<string, mixed> $document
     * @return array{canonical_json:string,sha256:string}
     */
    public function canonicalize(array $document): array
    {
        $document['schema'] = self::SCHEMA;
        $canonical = $this->canonicalValue($document);
        if (!is_array($canonical) || array_is_list($canonical)) {
            throw new \InvalidArgumentException('Qur’an release manifest must be a JSON object.');
        }
        $json = json_encode(
            $canonical,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
        if (str_starts_with($json, "\xEF\xBB\xBF")) {
            throw new \InvalidArgumentException('Qur’an release manifest is not canonical UTF-8 JSON.');
        }

        return ['canonical_json' => $json, 'sha256' => hash('sha256', $json)];
    }

    private function canonicalValue(mixed $value): mixed
    {
        if (is_float($value) || is_resource($value) || is_object($value)) {
            throw new \InvalidArgumentException('Qur’an release manifests do not permit floating point, resource, or object values.');
        }
        if (is_string($value)) {
            if (str_contains($value, "\0") || !mb_check_encoding($value, 'UTF-8')) {
                throw new \InvalidArgumentException('Qur’an release manifest contains an invalid UTF-8 string.');
            }

            return $value;
        }
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map($this->canonicalValue(...), $value);
        }

        $canonical = [];
        foreach ($value as $key => $entry) {
            if (!is_string($key) || $key === '' || str_contains($key, "\0") || !mb_check_encoding($key, 'UTF-8')) {
                throw new \InvalidArgumentException('Qur’an release manifest contains an invalid object key.');
            }
            $canonical[$key] = $this->canonicalValue($entry);
        }
        ksort($canonical, SORT_STRING);

        return $canonical;
    }
}
