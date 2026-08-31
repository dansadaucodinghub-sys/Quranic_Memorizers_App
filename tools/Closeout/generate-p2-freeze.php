<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Qmdb\Tools\Closeout\P2FreezeGenerator;

try {
    $result = (new P2FreezeGenerator())->generate(QMDB_PROJECT_ROOT);
    printf("P2 freeze: GENERATED (status=%s, files=%d, sha256=%s)\n", $result['status'], $result['files'], $result['sha256']);
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'P2 freeze generation: FAIL - ' . $exception->getMessage() . "\n");
    exit(1);
}
