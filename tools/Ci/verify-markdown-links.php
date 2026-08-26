<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Qmdb\Tools\Ci\MarkdownLinkVerifier;

$report = (new MarkdownLinkVerifier(QMDB_PROJECT_ROOT))->verify();
foreach ($report->errors() as $error) {
    fwrite(STDERR, "ERROR: {$error}\n");
}
printf("Markdown links: %s (%d checks)\n", $report->passed() ? 'PASS' : 'FAIL', $report->checks());
exit($report->passed() ? 0 : 1);
