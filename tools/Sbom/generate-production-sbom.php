<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Qmdb\Tools\Sbom\ProductionSbomGenerator;

$output = QMDB_PROJECT_ROOT . '/build/reports/production-sbom.cdx.json';
$rawArguments = is_array($_SERVER['argv'] ?? null) ? $_SERVER['argv'] : [];
$arguments = array_values(array_filter($rawArguments, is_string(...)));
foreach (array_slice($arguments, 1) as $argument) {
    if (str_starts_with($argument, '--output=')) {
        $candidate = substr($argument, 9);
        if ($candidate === '' || str_contains($candidate, "\0")) {
            fwrite(STDERR, "Invalid SBOM output path.\n");
            exit(64);
        }
        $output = str_starts_with($candidate, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $candidate) === 1
            ? $candidate
            : QMDB_PROJECT_ROOT . '/' . $candidate;
    } else {
        fwrite(STDERR, "Unknown option: {$argument}\n");
        exit(64);
    }
}
try {
    $hash = (new ProductionSbomGenerator())->write(QMDB_PROJECT_ROOT, $output);
    printf("Production SBOM: PASS (%s, sha256:%s)\n", $output, $hash);
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Production SBOM: FAIL (' . $exception->getMessage() . ")\n");
    exit(1);
}
