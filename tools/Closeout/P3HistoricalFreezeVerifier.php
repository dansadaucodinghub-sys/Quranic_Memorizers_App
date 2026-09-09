<?php

declare(strict_types=1);

namespace Qmdb\Tools\Closeout;

use Qmdb\Tools\Ci\VerificationReport;
use Qmdb\Tools\Support\ProcessRunner;

/** Verifies P3-FRZ-001 as immutable historical evidence after its governed successor exists. */
final class P3HistoricalFreezeVerifier
{
    /** @var list<string> */
    private const GOVERNANCE_MUTABLE_PATHS = [
        'tools/Ci/verify-p3-freeze.php',
        'tools/Ci/FrozenBaselineVerifier.php',
        'tools/Closeout/P2FreezePolicy.php',
        'tests/Tools/Ci/FrozenBaselineVerifierTest.php',
    ];

    public function verify(string $root): VerificationReport
    {
        $report = new VerificationReport();
        $path = $root . '/docs/closeout/p3/qmdb-p3-people-geography-organizations-participation-freeze.yaml';
        $yaml = file_get_contents($path);
        if (!is_string($yaml)) {
            $report->check(false, 'Historical P3 freeze manifest is unreadable.');
            return $report;
        }
        $report->check(str_contains($yaml, 'freeze_id: QMDB-P3-FRZ-001'), 'Historical P3 freeze identifier is missing.');
        preg_match('/^  source_revision: "([a-f0-9]{40})"$/m', $yaml, $revision);
        $report->check(isset($revision[1]), 'Historical P3 freeze source revision is invalid.');
        preg_match_all('/^    - path: "([^"]+)"\R\s+category: [A-Z0-9_]+\R\s+sha256: ([a-f0-9]{64})$/m', $yaml, $files, PREG_SET_ORDER);
        $report->check(count($files) > 1_000, 'Historical P3 freeze inventory is incomplete.');
        $runner = new ProcessRunner();
        foreach ($files as $file) {
            $relative = $file[1];
            $expected = $file[2];
            if (in_array($relative, self::GOVERNANCE_MUTABLE_PATHS, true)) {
                $historic = $runner->run(['git', 'show', $revision[1] . ':' . $relative], $root);
                $report->check($historic->exitCode === 0 && hash('sha256', $historic->stdout) === $expected, 'Historical governance file does not match P3-FRZ-001: ' . $relative);
                continue;
            }
            $absolute = $root . '/' . $relative;
            $report->check(is_file($absolute) && !is_link($absolute), 'Historical P3 governed file is unavailable: ' . $relative);
            if (is_file($absolute) && !is_link($absolute)) {
                $report->check(hash_file('sha256', $absolute) === $expected, 'Historical P3 product checksum mismatch: ' . $relative);
            }
        }
        $status = $runner->run(['git', 'status', '--porcelain=v1', '--untracked-files=all'], $root);
        $report->check($status->exitCode === 0 && trim($status->stdout) === '', 'Historical P3 verification requires a clean working tree.');
        return $report;
    }
}
