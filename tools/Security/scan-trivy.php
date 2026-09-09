<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Qmdb\Tools\Security\TrivyDatabaseException;
use Qmdb\Tools\Security\TrivyFilesystemScanner;

$rawArguments = $_SERVER['argv'] ?? [];
if (!is_array($rawArguments)) {
    $rawArguments = [];
}
$arguments = [];
foreach ($rawArguments as $argument) {
    if (is_string($argument)) {
        $arguments[] = $argument;
    }
}
$mode = $arguments[1] ?? 'repository';
$target = $arguments[2] ?? QMDB_PROJECT_ROOT;
if (!str_starts_with($target, '/') && preg_match('/^[A-Za-z]:[\\\\\/]/', $target) !== 1) {
    $target = QMDB_PROJECT_ROOT . '/' . $target;
}
try {
    $result = (new TrivyFilesystemScanner())->scan(QMDB_PROJECT_ROOT, $target, $mode);
    fwrite(STDOUT, 'Trivy ' . $mode . ' filesystem scan: PASS (' . $result['database']['next_update'] . ")\n");
    exit(0);
} catch (TrivyDatabaseException $exception) {
    fwrite(STDERR, 'Trivy ' . $mode . ' filesystem scan: FAIL [' . $exception->getCode() . '] ' . $exception->getMessage() . "\n");
    exit($exception->getCode());
} catch (Throwable $exception) {
    fwrite(STDERR, 'Trivy ' . $mode . ' filesystem scan: FAIL [' . TrivyDatabaseException::EXECUTION_FAILURE . '] ' . $exception->getMessage() . "\n");
    exit(TrivyDatabaseException::EXECUTION_FAILURE);
}
