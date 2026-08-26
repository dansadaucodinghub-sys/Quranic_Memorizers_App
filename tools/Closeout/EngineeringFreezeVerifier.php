<?php

declare(strict_types=1);

namespace Qmdb\Tools\Closeout;

use Qmdb\Tools\Ci\VerificationReport;
use Qmdb\Tools\Policy\PathPolicy;
use Qmdb\Tools\Support\ProcessRunner;

final readonly class EngineeringFreezeVerifier
{
    public function __construct(private EngineeringFreezePolicy $policy = new EngineeringFreezePolicy())
    {
    }

    public function verify(string $root): VerificationReport
    {
        $report = new VerificationReport();
        $path = $root . '/docs/closeout/p1/qmdb-p1-engineering-freeze.yaml';
        $yaml = file_get_contents($path);
        if (!is_string($yaml)) {
            $report->check(false, 'Engineering freeze candidate is unreadable.');
            return $report;
        }
        $requiredIdentities = [
            'engineering_freeze: QMDB-P1-FRZ-001',
            'freeze_status: APPROVED',
            'readiness: P1_COMPLETE',
            'source_state: governed_clean',
            'completed_batches:',
            'p1_blockers: []',
            'p2_blockers: []',
            'status: AUTHORIZED_BY_QMDB_RECOVERY_RUN_001',
        ];
        foreach ($requiredIdentities as $identity) {
            $report->check(str_contains($yaml, $identity), 'Missing candidate identity: ' . $identity);
        }
        $report->check(!str_contains($yaml, str_replace('/', '\\', $root)), 'Manifest contains an absolute root path.');
        $report->check(!preg_match('/^[A-Za-z]:[\\\\\/]/m', $yaml), 'Manifest contains a Windows absolute path.');

        preg_match_all(
            '/^\s+- path: "([^"]+)"\R\s+category: ([A-Z_]+)\R\s+sha256: ([a-f0-9]{64})\s*$/m',
            $yaml,
            $matches,
            PREG_SET_ORDER,
        );
        $report->check(count($matches) > 500, 'Engineering candidate must govern more than 500 source files.');
        $paths = [];
        foreach ($matches as $match) {
            $relative = $match[1];
            $paths[] = $relative;
            $report->check(!str_contains($relative, '\\'), 'Manifest path must use forward slashes: ' . $relative);
            $report->check(!str_starts_with($relative, '/'), 'Manifest path must be relative: ' . $relative);
            $report->check(
                $relative !== 'docs/closeout/p1/qmdb-p1-engineering-freeze.yaml',
                'Manifest must exclude itself.',
            );
            $report->check(
                in_array($match[2], [EngineeringFreezePolicy::FROZEN, EngineeringFreezePolicy::EXTENSION], true),
                'Unknown engineering category: ' . $match[2],
            );
            $absolute = $root . '/' . $relative;
            $report->check(
                is_file($absolute) && !is_link($absolute),
                'Governed file is missing or a symlink: ' . $relative,
            );
            if (is_file($absolute) && !is_link($absolute)) {
                $report->check(hash_file('sha256', $absolute) === $match[3], 'Checksum mismatch: ' . $relative);
            }
        }
        $report->check($paths === array_values(array_unique($paths)), 'Engineering paths must be unique.');
        $sorted = $paths;
        sort($sorted, SORT_STRING);
        $report->check($paths === $sorted, 'Engineering paths must be sorted deterministically.');
        $expected = array_column($this->policy->entries($root), 'path');
        $report->check($paths === $expected, 'Engineering candidate file inventory has drifted.');

        foreach (EngineeringFreezePolicy::DYNAMIC_PATHS as $dynamic) {
            $report->check(str_contains($yaml, '    - ' . $dynamic), 'Dynamic exclusion missing: ' . $dynamic);
        }
        foreach (EngineeringFreezePolicy::GENERATED_PATHS as $generated) {
            $report->check(str_contains($yaml, '    - ' . $generated), 'Generated exclusion missing: ' . $generated);
        }
        $this->verifyGovernedRevision($root, $yaml, $report);
        $report->check(
            (new PathPolicy())->repositoryViolation('docs/closeout/p1/qmdb-p1-engineering-freeze.yaml') === null,
            'Engineering candidate path violates repository policy.',
        );
        return $report;
    }

    private function verifyGovernedRevision(string $root, string $yaml, VerificationReport $report): void
    {
        $matched = preg_match('/^\s*source_revision: "([a-f0-9]{40})"\s*$/m', $yaml, $matches) === 1;
        $report->check($matched, 'Engineering freeze source revision is missing or invalid.');
        if (!$matched) {
            return;
        }

        $revision = $matches[1];
        $runner = new ProcessRunner();
        $ancestor = $runner->run(['git', 'merge-base', '--is-ancestor', $revision, 'HEAD'], $root);
        $report->check($ancestor->exitCode === 0, 'Engineering source revision is not an ancestor of HEAD.');

        $revisionDiff = $runner->run(['git', 'diff', '--name-only', $revision, 'HEAD', '--'], $root);
        $report->check($revisionDiff->exitCode === 0, 'Unable to compare the engineering source revision with HEAD.');
        if ($revisionDiff->exitCode === 0) {
            foreach ($this->paths($revisionDiff->stdout) as $path) {
                $report->check(
                    !$this->policy->isIncluded($path),
                    'Governed file changed after the recorded source revision: ' . $path,
                );
            }
        }

        $status = $runner->run(['git', 'status', '--porcelain=v1', '--untracked-files=all'], $root);
        $report->check($status->exitCode === 0, 'Unable to inspect governed working-tree state.');
        if ($status->exitCode === 0) {
            foreach (preg_split('/\R/', trim($status->stdout)) ?: [] as $line) {
                if (strlen($line) < 4) {
                    continue;
                }
                $path = substr($line, 3);
                if (str_contains($path, ' -> ')) {
                    $path = substr($path, (int) strrpos($path, ' -> ') + 4);
                }
                $path = trim($path, '"');
                $report->check(
                    !$this->policy->isIncluded($path),
                    'Governed working-tree path is not committed: ' . $path,
                );
            }
        }
    }

    /** @return list<string> */
    private function paths(string $output): array
    {
        return array_values(array_filter(
            array_map(static fn (string $path): string => trim($path), preg_split('/\R/', trim($output)) ?: []),
            static fn (string $path): bool => $path !== '',
        ));
    }
}
