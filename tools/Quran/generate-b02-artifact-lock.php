<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$base = $root . '/resources/data/quran/tanzil';
$noticePath = $base . '/NOTICE.txt';
$lockPath = $base . '/b02-source-artifacts.lock.json';
$acquiredAt = '2026-09-10T18:56:29Z';
$artifacts = [
    artifact($root, $base . '/uthmani-1.1/quran-uthmani.txt', 'TANZIL_UTHMANI_TEXT_1_1', 'TANZIL_UTHMANI_1_1', '1.1', 'CANONICAL_TEXT', 'TANZIL_UTHMANI_PIPE_V1', 'text/plain', 'https://tanzil.net/download/', $acquiredAt),
    artifact($root, $base . '/metadata-1.0/quran-data.xml', 'TANZIL_QURAN_METADATA_1_0', 'TANZIL_QURAN_METADATA_1_0', '1.0', 'STRUCTURAL_METADATA', 'TANZIL_QURAN_METADATA_XML_V1', 'application/xml', 'https://tanzil.net/docs/quran_metadata', $acquiredAt),
];
$payload = ['artifacts' => $artifacts, 'canonical_serialization_version' => 'qmdb.quran.canonical-json.v1', 'notice_path' => relative($root, $noticePath), 'notice_sha256' => checksum($noticePath), 'schema' => 'qmdb.quran.b02-source-artifacts-lock.v1'];
$payload['lock_sha256'] = hash('sha256', canonical($payload));
file_put_contents($lockPath, canonical($payload) . "\n", LOCK_EX) !== false || exit(1);
fwrite(STDOUT, "B02 artifact lock generated\n");

/** @return array<string, int|string> */
function artifact(string $root, string $path, string $artifactCode, string $sourceCode, string $sourceVersion, string $role, string $profile, string $mediaType, string $reference, string $acquiredAt): array
{
    $size = filesize($path);
    if (!is_int($size)) {
        throw new RuntimeException('Approved Tanzil artifact size is unavailable.');
    }
    return ['acquired_at_utc' => $acquiredAt, 'artifact_code' => $artifactCode, 'artifact_role' => $role, 'byte_size' => $size, 'format_profile' => $profile, 'media_type' => $mediaType, 'official_reference' => $reference, 'original_filename' => basename($path), 'repository_relative_path' => relative($root, $path), 'sha256' => checksum($path), 'source_code' => $sourceCode, 'source_version' => $sourceVersion];
}

function checksum(string $path): string
{
    if (!is_file($path) || ($hash = hash_file('sha256', $path)) === false) {
        throw new RuntimeException('Approved Tanzil artifact is missing.');
    }

    return $hash;
}

function relative(string $root, string $path): string
{
    return str_replace('\\', '/', substr($path, strlen($root) + 1));
}

/** @param array<array-key, mixed> $value */
function canonical(array $value): string
{
    sortKeys($value);

    return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/** @param array<array-key, mixed> $value */
function sortKeys(array &$value): void
{
    foreach ($value as &$entry) {
        if (is_array($entry)) {
            sortKeys($entry);
        }
    }
    unset($entry);
    if (!array_is_list($value)) {
        ksort($value, SORT_STRING);
    }
}
