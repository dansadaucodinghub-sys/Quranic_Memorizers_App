<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Qmdb\Tools\Ci\RepositoryPolicyVerifier;

$report = (new RepositoryPolicyVerifier(QMDB_PROJECT_ROOT))->verify();
foreach ($report->errors() as $error) {
    fwrite(STDERR, "ERROR: {$error}\n");
}
foreach ($report->warnings() as $warning) {
    fwrite(STDERR, "WARNING: {$warning}\n");
}
printf("Repository policy: %s (%d checks)\n", $report->passed() ? 'PASS' : 'FAIL', $report->checks());
exit($report->passed() ? 0 : 1);
