<?php

declare(strict_types=1);

namespace Qmdb\Tools\Closeout;

use Qmdb\Tools\Ci\VerificationReport;
use Qmdb\Tools\Support\ProcessRunner;

final readonly class P2FreezeVerifier
{
    public function __construct(private P2FreezePolicy $policy = new P2FreezePolicy())
    {
    }

    public function verify(string $root): VerificationReport
    {
        $report = new VerificationReport();
        $path = $root . '/docs/closeout/p2/qmdb-p2-identity-security-tenancy-freeze.yaml';
        $yaml = file_get_contents($path);
        if (!is_string($yaml)) {
            $report->check(false, 'P2 freeze manifest is unreadable.');

            return $report;
        }
        foreach (
            [
                'freeze_id: QMDB-P2-FRZ-001',
                'phase: P2',
                'status: FROZEN',
                'source_state: clean',
                'frozen_product_baseline: QMDB-P0-FRZ-001',
                'frozen_engineering_baseline: QMDB-P1-FRZ-001',
                'identifier: P3',
                'status: NOT_STARTED_NOT_AUTHORIZED',
                'blocking: false',
                'release_eligible: true',
            ] as $required
        ) {
            $report->check(str_contains($yaml, $required), 'P2 freeze identity is missing: ' . $required);
        }
        $report->check(!preg_match('/^[A-Za-z]:[\\\\\/]/m', $yaml), 'P2 freeze contains a Windows absolute path.');
        preg_match_all('/^\s+- path: "([^"]+)"\R\s+category: ([A-Z_]+)\R\s+sha256: ([a-f0-9]{64})\s*$/m', $yaml, $matches, PREG_SET_ORDER);
        $report->check(count($matches) > 1_000, 'P2 freeze must govern the P2 source and evidence boundary.');
        $paths = [];
        foreach ($matches as $match) {
            $relative = $match[1];
            $paths[] = $relative;
            $report->check(!str_contains($relative, '\\') && !str_starts_with($relative, '/'), 'P2 freeze path is invalid: ' . $relative);
            $report->check($relative !== 'docs/closeout/p2/qmdb-p2-identity-security-tenancy-freeze.yaml', 'P2 freeze must exclude itself.');
            $report->check(in_array($match[2], [P2FreezePolicy::FROZEN, P2FreezePolicy::EXTENSION], true), 'Unknown P2 freeze category: ' . $match[2]);
            $absolute = $root . '/' . $relative;
            $report->check(is_file($absolute) && !is_link($absolute), 'P2 governed file is unavailable: ' . $relative);
            if (is_file($absolute) && !is_link($absolute)) {
                $report->check(hash_file('sha256', $absolute) === $match[3], 'P2 checksum mismatch: ' . $relative);
            }
        }
        $expected = array_column($this->policy->entries($root), 'path');
        $report->check($paths === $expected, 'P2 freeze governed-file inventory has drifted.');
        $report->check($paths === array_values(array_unique($paths)), 'P2 freeze paths must be unique.');
        $sorted = $paths;
        sort($sorted, SORT_STRING);
        $report->check($paths === $sorted, 'P2 freeze paths must be deterministically sorted.');
        foreach (
            [
                '00-index.md',
                '01-executive-closeout-summary.md',
                '02-batch-and-requirement-verification.md',
                '03-security-and-threat-verification.md',
                '04-data-schema-and-tenant-isolation-verification.md',
                '05-accessibility-and-progressive-interaction-verification.md',
                '06-operations-release-and-deferred-evidence.md',
                '07-p3-readiness-assessment.md',
            ] as $document
        ) {
            $report->check(is_file($root . '/docs/closeout/p2/' . $document), 'Required P2 closeout document is missing: ' . $document);
        }
        for ($batch = 1; $batch <= 10; ++$batch) {
            $report->check(is_file($root . sprintf('/docs/implementation/reports/QMDB-P2-B%02d-implementation-report.md', $batch)), 'Required P2 batch report is missing.');
        }
        $state = file_get_contents($root . '/docs/project/project-state.md');
        $report->check(is_string($state) && str_contains($state, 'P2 Status: COMPLETE / FROZEN'), 'Project state does not close P2.');
        $report->check(is_string($state) && str_contains($state, 'P3 Status: NOT STARTED / NOT AUTHORIZED'), 'Project state does not preserve P3 authorization boundary.');
        $this->verifyRevision($root, $yaml, $report);
        $this->verifyNoP3Production($root, $report);

        return $report;
    }

    private function verifyRevision(string $root, string $yaml, VerificationReport $report): void
    {
        $matched = preg_match('/^\s*source_revision: "([a-f0-9]{40})"\s*$/m', $yaml, $matches) === 1;
        $report->check($matched, 'P2 freeze source revision is missing or invalid.');
        if (!$matched) {
            return;
        }
        $runner = new ProcessRunner();
        $ancestor = $runner->run(['git', 'merge-base', '--is-ancestor', $matches[1], 'HEAD'], $root);
        $report->check($ancestor->exitCode === 0, 'P2 freeze source revision is not an ancestor of HEAD.');
        $status = $runner->run(['git', 'status', '--porcelain=v1', '--untracked-files=all'], $root);
        $report->check($status->exitCode === 0 && trim($status->stdout) === '', 'P2 freeze verification requires a clean working tree.');
    }

    private function verifyNoP3Production(string $root, VerificationReport $report): void
    {
        foreach (['Geography', 'Organizations', 'People', 'Guardianship'] as $module) {
            $report->check(!is_dir($root . '/src/Modules/' . $module), 'Unapproved P3 production module exists: ' . $module);
        }
        foreach (glob($root . '/database/migrations/*P3*') ?: [] as $migration) {
            $report->check(false, 'Unapproved P3 migration exists: ' . basename($migration));
        }
        foreach (glob($root . '/database/seeds/*P3*') ?: [] as $seed) {
            $report->check(false, 'Unapproved P3 seed exists: ' . basename($seed));
        }
    }
}
