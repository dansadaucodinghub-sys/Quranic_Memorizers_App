<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Qmdb\Tools\Closeout\P2FreezeVerifier;

$report = (new P2FreezeVerifier())->verify(QMDB_PROJECT_ROOT);
foreach ($report->errors() as $error) {
    fwrite(STDERR, "ERROR: {$error}\n");
}
printf("P2 freeze: %s (%d checks)\n", $report->passed() ? 'PASS' : 'FAIL', $report->checks());
exit($report->passed() ? 0 : 1);
