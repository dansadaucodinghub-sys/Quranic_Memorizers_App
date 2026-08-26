<?php

declare(strict_types=1);

use Qmdb\Tools\Closeout\EngineeringFreezeGenerator;

require dirname(__DIR__) . '/bootstrap.php';

try {
    $result = (new EngineeringFreezeGenerator())->generate(QMDB_PROJECT_ROOT);
    fwrite(STDOUT, sprintf(
        "Engineering freeze: GENERATED (status=%s, files=%d, sha256=%s)\n",
        $result['status'],
        $result['files'],
        $result['sha256'],
    ));
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Engineering freeze generation: FAIL - ' . $exception->getMessage() . "\n");
    exit(1);
}
