<?php

/**
 * Generates the committed Tanzil artifact lock from files acquired through the
 * approved engineering-time process. It performs no network operation.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$base = $root . '/resources/data/quran/tanzil';
$canonicalPath = $base . '/uthmani-1.1/quran-uthmani.txt';
$metadataPath = $base . '/metadata-1.0/quran-data.xml';
$simplePath = $base . '/simple-clean-1.1/quran-simple-clean.txt';
$noticePath = $base . '/NOTICE.txt';
$lockPath = $base . '/search-artifacts.lock.json';

foreach ([$canonicalPath, $metadataPath, $simplePath] as $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing approved Tanzil artifact.\n");
        exit(1);
    }
}

$canonical = file_get_contents($canonicalPath);
if (!is_string($canonical)) {
    fwrite(STDERR, "Unable to read canonical Tanzil artifact.\n");
    exit(1);
}

$noticeOffset = strpos($canonical, '# PLEASE DO NOT REMOVE OR CHANGE THIS COPYRIGHT BLOCK');
if ($noticeOffset === false) {
    fwrite(STDERR, "Canonical Tanzil notice is missing.\n");
    exit(1);
}

$notice = substr($canonical, $noticeOffset);
if (file_put_contents($noticePath, $notice, LOCK_EX) === false) {
    fwrite(STDERR, "Unable to preserve Tanzil notice.\n");
    exit(1);
}

$acquiredAt = existingAcquisitionTime($lockPath) ?? gmdate('Y-m-d\TH:i:s\Z');
$artifacts = [
    artifact($root, $canonicalPath, 'TANZIL_UTHMANI_1_1', '1.1', 'TANZIL_UTHMANI_TEXT_1_1', 'CANONICAL_TEXT', 'UTF8_TEXT_WITH_AYAH_IDENTIFIERS', 'text/plain', 'https://tanzil.net/pub/download/index.php?quranType=uthmani&outType=txt-2&agree=true&marks=true&sajdah=true&alef=true&tatweel=true', $acquiredAt),
    artifact($root, $metadataPath, 'TANZIL_QURAN_METADATA_1_0', '1.0', 'TANZIL_QURAN_METADATA_1_0', 'STRUCTURAL_METADATA', 'XML', 'application/xml', 'https://tanzil.net/res/text/metadata/quran-data.xml', $acquiredAt),
    artifact($root, $simplePath, 'TANZIL_SIMPLE_CLEAN_1_1', '1.1', 'TANZIL_SIMPLE_CLEAN_TEXT_1_1', 'SEARCH_TEXT', 'UTF8_TEXT_WITH_AYAH_IDENTIFIERS', 'text/plain', 'https://tanzil.net/pub/download/index.php?quranType=simple-clean&outType=txt-2&agree=true', $acquiredAt),
];

$payload = [
    'artifacts' => $artifacts,
    'notice' => [
        'repository_relative_path' => relative($root, $noticePath),
        'sha256' => hashFile($noticePath),
    ],
    'schema' => 'qmdb.quran.tanzil-artifacts-lock.v1',
];
$lock = [
    'artifacts' => $payload['artifacts'],
    'lock_sha256' => hash('sha256', encode($payload)),
    'notice' => $payload['notice'],
    'schema' => $payload['schema'],
];

if (file_put_contents($lockPath, encode($lock) . "\n", LOCK_EX) === false) {
    fwrite(STDERR, "Unable to write Tanzil artifact lock.\n");
    exit(1);
}

fwrite(STDOUT, sprintf("Tanzil artifact lock generated: %s\n", relative($root, $lockPath)));

/** @return array<string, int|string> */
function artifact(
    string $root,
    string $path,
    string $sourceCode,
    string $sourceVersion,
    string $artifactCode,
    string $role,
    string $formatProfile,
    string $mediaType,
    string $officialReference,
    string $acquiredAt,
): array {
    return [
        'acquired_at_utc' => $acquiredAt,
        'artifact_code' => $artifactCode,
        'byte_size' => filesize($path),
        'format_profile' => $formatProfile,
        'media_type' => $mediaType,
        'official_reference' => $officialReference,
        'original_filename' => basename($path),
        'repository_relative_path' => relative($root, $path),
        'role' => $role,
        'sha256' => hashFile($path),
        'source_code' => $sourceCode,
        'source_version' => $sourceVersion,
    ];
}

function encode(array $value): string
{
    return json_encode(
        $value,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
    );
}

function hashFile(string $path): string
{
    $hash = hash_file('sha256', $path);
    if (!is_string($hash)) {
        throw new RuntimeException('Unable to checksum approved Tanzil artifact.');
    }

    return $hash;
}

function relative(string $root, string $path): string
{
    return str_replace('\\', '/', substr($path, strlen($root) + 1));
}

function existingAcquisitionTime(string $lockPath): ?string
{
    if (!is_file($lockPath)) {
        return null;
    }

    $contents = file_get_contents($lockPath);
    if (!is_string($contents)) {
        return null;
    }

    try {
        $lock = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return null;
    }

    $value = $lock['artifacts'][0]['acquired_at_utc'] ?? null;

    return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value) === 1
        ? $value
        : null;
}
