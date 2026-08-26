<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Qmdb\Tools\Build\ReleaseBuilder;

try {
    $result = (new ReleaseBuilder())->build(QMDB_PROJECT_ROOT);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Release build: FAIL (' . $exception->getMessage() . ")\n");
    exit(1);
}
