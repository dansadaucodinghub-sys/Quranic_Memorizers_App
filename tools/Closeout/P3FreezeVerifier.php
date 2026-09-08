<?php

declare(strict_types=1);

namespace Qmdb\Tools\Closeout;

use Qmdb\Tools\Ci\VerificationReport;
use Qmdb\Tools\Support\ProcessRunner;

final readonly class P3FreezeVerifier
{
    public function __construct(private P3FreezePolicy $policy = new P3FreezePolicy())
    {
    }

    public function verify(string $root): VerificationReport
    {
        $report = new VerificationReport();
        $yaml = file_get_contents($root . '/docs/closeout/p3/qmdb-p3-people-geography-organizations-participation-freeze.yaml');
        if (!is_string($yaml)) {
            $report->check(false, 'P3 freeze manifest is unreadable.');
            return $report;
        }
        foreach (['freeze_id: QMDB-P3-FRZ-001', 'phase: P3', 'closeout: QMDB-P3-CLOSE', 'status: FROZEN', 'source_state: clean'] as $required) {
            $report->check(str_contains($yaml, $required), 'P3 freeze identity is missing: ' . $required);
        }
        preg_match_all('/^    - path: "([^"]+)"\R\s+category: ([A-Z0-9_]+)\R\s+sha256: ([a-f0-9]{64})\s*$/m', $yaml, $matches, PREG_SET_ORDER);
        $paths = [];
        foreach ($matches as $match) {
            $paths[] = $match[1];
            $absolute = $root . '/' . $match[1];
            $report->check(is_file($absolute) && !is_link($absolute), 'P3 governed file is unavailable: ' . $match[1]);
            if (is_file($absolute) && !is_link($absolute)) {
                $report->check(hash_file('sha256', $absolute) === $match[3], 'P3 checksum mismatch: ' . $match[1]);
            }
            $report->check(in_array($match[2], [P3FreezePolicy::FROZEN, P3FreezePolicy::EXTENSION], true), 'P3 category is invalid.');
        }
        $report->check($paths === array_column($this->policy->entries($root), 'path'), 'P3 freeze governed-file inventory has drifted.');
        $report->check($paths === array_values(array_unique($paths)), 'P3 freeze paths must be unique.');
        $this->verifyRevision($root, $yaml, $report);

        return $report;
    }

    private function verifyRevision(string $root, string $yaml, VerificationReport $report): void
    {
        $matched = preg_match('/^\s*source_revision: "([a-f0-9]{40})"\s*$/m', $yaml, $matches) === 1;
        $report->check($matched, 'P3 freeze source revision is missing or invalid.');
        if (!$matched) {
            return;
        }
        $runner = new ProcessRunner();
        $ancestor = $runner->run(['git', 'merge-base', '--is-ancestor', $matches[1], 'HEAD'], $root);
        $report->check($ancestor->exitCode === 0, 'P3 freeze source revision is not an ancestor of HEAD.');
        $status = $runner->run(['git', 'status', '--porcelain=v1', '--untracked-files=all'], $root);
        $report->check($status->exitCode === 0 && trim($status->stdout) === '', 'P3 freeze verification requires a clean working tree.');
    }
}
