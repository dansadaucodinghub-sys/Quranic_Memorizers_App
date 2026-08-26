<?php

declare(strict_types=1);

use Qmdb\Tools\Closeout\EngineeringFreezeVerifier;

require dirname(__DIR__) . '/bootstrap.php';

$report = (new EngineeringFreezeVerifier())->verify(QMDB_PROJECT_ROOT);
foreach ($report->errors() as $error) {
    fwrite(STDERR, 'ERROR: ' . $error . "\n");
}
if (!$report->passed()) {
    fwrite(STDERR, sprintf("Engineering freeze candidate: FAIL (%d checks)\n", $report->checks()));
    exit(1);
}
fwrite(STDOUT, sprintf("Engineering freeze candidate: PASS (%d checks)\n", $report->checks()));
