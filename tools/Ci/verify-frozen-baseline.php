<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Qmdb\Tools\Ci\FrozenBaselineVerifier;

$report = (new FrozenBaselineVerifier(QMDB_PROJECT_ROOT))->verify();
foreach ($report->errors() as $error) {
    fwrite(STDERR, "ERROR: {$error}\n");
}
printf("Frozen baseline: %s (%d checks)\n", $report->passed() ? 'PASS' : 'FAIL', $report->checks());
exit($report->passed() ? 0 : 1);
