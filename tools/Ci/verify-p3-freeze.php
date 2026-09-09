<?php

declare(strict_types=1);

use Qmdb\Tools\Closeout\P3HistoricalFreezeVerifier;

require dirname(__DIR__) . '/bootstrap.php';

$report = (new P3HistoricalFreezeVerifier())->verify(QMDB_PROJECT_ROOT);
foreach ($report->errors() as $error) {
    fwrite(STDERR, 'ERROR: ' . $error . "\n");
}
printf("P3 freeze: %s (%d checks)\n", $report->passed() ? 'PASS' : 'FAIL', $report->checks());
exit($report->passed() ? 0 : 1);
