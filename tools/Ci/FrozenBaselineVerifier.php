<?php

declare(strict_types=1);

namespace Qmdb\Tools\Ci;

final class FrozenBaselineVerifier
{
    public function __construct(private readonly string $root)
    {
    }

    public function verify(): VerificationReport
    {
        $report = new VerificationReport();
        $manifestPath = $this->root . '/docs/closeout/qmdb-p0-baseline-freeze.yaml';
        $manifest = file_get_contents($manifestPath);
        if (!is_string($manifest)) {
            $report->check(false, 'Frozen baseline manifest is unreadable.');
            return $report;
        }
        $report->check(
            preg_match('/^\s*source_baseline:\s*QMDB-BL-001\s*$/m', $manifest) === 1,
            'Unexpected source baseline identity.',
        );
        $report->check(
            preg_match('/^\s*freeze_id:\s*QMDB-P0-FRZ-001\s*$/m', $manifest) === 1,
            'Unexpected frozen baseline identity.',
        );

        preg_match_all(
            '/^\s+- path: "([^"]+)"\R\s+category: [^\r\n]+\R\s+sha256: ([a-f0-9]{64})\s*$/m',
            $manifest,
            $matches,
            PREG_SET_ORDER,
        );
        $report->check(count($matches) === 82, 'Frozen manifest must contain exactly 82 governed file hashes.');
        $paths = [];
        foreach ($matches as $match) {
            $relative = $match[1];
            $paths[] = $relative;
            $absolute = $this->root . '/' . $relative;
            $report->check(is_file($absolute), 'Frozen file is missing: ' . $relative);
            if (is_file($absolute)) {
                $report->check(hash_file('sha256', $absolute) === $match[2], 'Frozen checksum mismatch: ' . $relative);
            }
        }
        $report->check(count(array_unique($paths)) === count($paths), 'Frozen manifest paths must be unique.');

        $excludedBlock = '';
        $exclusionsPattern = '/\R\s*excluded_dynamic_files:\R(?<block>[\s\S]+?)\R\s*resolved_decisions:/';
        if (preg_match($exclusionsPattern, $manifest, $block) === 1) {
            $excludedBlock = $block['block'];
        }
        preg_match_all('/^\s+- path:\s*([^\s"]+)\s*$/m', $excludedBlock, $excludedMatches);
        $excluded = $excludedMatches[1];
        $report->check(count($excluded) === 8, 'Frozen manifest must retain eight explicit dynamic exclusions.');
        foreach (
            [
                'docs/project/project-state.md',
                'docs/project/open-decisions.md',
                'docs/project/risk-register.md',
                'docs/closeout/qmdb-p0-baseline-freeze.yaml',
            ] as $required
        ) {
            $report->check(in_array($required, $excluded, true), 'Required dynamic exclusion is missing: ' . $required);
            $report->check(!in_array($required, $paths, true), 'Dynamic file must not also be frozen: ' . $required);
        }
        return $report;
    }
}
