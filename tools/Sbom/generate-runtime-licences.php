<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Qmdb\Tools\Sbom\RuntimeLicenceGenerator;

try {
    $result = (new RuntimeLicenceGenerator())->generate(QMDB_PROJECT_ROOT, QMDB_PROJECT_ROOT . '/build/reports');
    printf(
        "Licence inventory: PASS (runtime=%d, development=%d, unknown-runtime=%d, review=%d)\n",
        $result['runtime_count'],
        $result['development_count'],
        $result['unknown_runtime'],
        $result['review_runtime'],
    );
    exit($result['unknown_runtime'] === 0 ? 0 : 2);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Licence inventory: FAIL (' . $exception->getMessage() . ")\n");
    exit(1);
}
