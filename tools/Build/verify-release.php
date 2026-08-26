<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Qmdb\Tools\Build\ReleaseArtifactVerifier;

$rawArguments = is_array($_SERVER['argv'] ?? null) ? $_SERVER['argv'] : [];
$arguments = array_values(array_filter($rawArguments, is_string(...)));
$archive = $arguments[1] ?? null;
if ($archive === null) {
    $matches = glob(QMDB_PROJECT_ROOT . '/build/release/qmdb-*.tar.gz') ?: [];
    sort($matches, SORT_STRING);
    $archive = $matches === [] ? null : end($matches);
}
if (!is_string($archive) || !is_file($archive)) {
    fwrite(STDERR, "Release verification: FAIL (artifact not found)\n");
    exit(1);
}
if (!str_starts_with($archive, '/') && preg_match('/^[A-Za-z]:[\\\\\/]/', $archive) !== 1) {
    $archive = QMDB_PROJECT_ROOT . '/' . $archive;
}
try {
    $result = (new ReleaseArtifactVerifier())->verify(QMDB_PROJECT_ROOT, $archive);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Release verification: FAIL (' . $exception->getMessage() . ")\n");
    exit(1);
}
