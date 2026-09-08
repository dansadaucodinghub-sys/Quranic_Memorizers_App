<?php

declare(strict_types=1);

use Qmdb\Tools\Closeout\P3FreezeGenerator;

require dirname(__DIR__) . '/bootstrap.php';

try {
    $result = (new P3FreezeGenerator())->generate(QMDB_PROJECT_ROOT);
    printf("P3 freeze: GENERATED (files=%d, sha256=%s)\n", $result['files'], $result['sha256']);
} catch (Throwable $exception) {
    fwrite(STDERR, 'P3 freeze generation: FAIL - ' . $exception->getMessage() . "\n");
    exit(1);
}
