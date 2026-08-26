<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Qmdb\Tools\Sbom\SbomValidator;

$rawArguments = is_array($_SERVER['argv'] ?? null) ? $_SERVER['argv'] : [];
$arguments = array_values(array_filter($rawArguments, is_string(...)));
$path = $arguments[1] ?? QMDB_PROJECT_ROOT . '/build/reports/production-sbom.cdx.json';
if (!str_starts_with($path, '/') && preg_match('/^[A-Za-z]:[\\\\\/]/', $path) !== 1) {
    $path = QMDB_PROJECT_ROOT . '/' . $path;
}
$report = (new SbomValidator())->validate(QMDB_PROJECT_ROOT, $path);
foreach ($report->errors() as $error) {
    fwrite(STDERR, "ERROR: {$error}\n");
}
printf("SBOM validation: %s (%d checks)\n", $report->passed() ? 'PASS' : 'FAIL', $report->checks());
exit($report->passed() ? 0 : 1);
