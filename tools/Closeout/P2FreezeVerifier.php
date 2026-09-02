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
        $authorizedP3Extension = is_file($root . '/docs/project/p3-p2-freeze-extension-ledger.yaml');
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
        preg_match_all('/^\s+- path: "([^"]+)"\R\s+category: ([A-Z0-9_]+)\R\s+sha256: ([a-f0-9]{64})\s*$/m', $yaml, $matches, PREG_SET_ORDER);
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
            if (
                $match[2] === P2FreezePolicy::FROZEN
                && is_file($absolute)
                && !is_link($absolute)
                && (!$authorizedP3Extension || !$this->policy->isP3MutableExistingPath($relative))
            ) {
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
        $this->verifyP3AuthorizationBoundary($root, is_string($state) ? $state : '', $report);
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
        $ledgerExists = is_file($root . '/docs/project/p3-p2-freeze-extension-ledger.yaml');
        foreach (['Guardianship'] as $module) {
            $report->check(!is_dir($root . '/src/Modules/' . $module), 'Unapproved P3 production module exists: ' . $module);
        }
        if (!$ledgerExists) {
            $report->check(!is_dir($root . '/src/Modules/Geography'), 'Unapproved P3 production module exists: Geography');
            $report->check(!is_dir($root . '/src/Modules/People'), 'Unapproved P3 production module exists: People');
        }
    }

    private function verifyP3AuthorizationBoundary(string $root, string $state, VerificationReport $report): void
    {
        $ledger = $root . '/docs/project/p3-p2-freeze-extension-ledger.yaml';
        if (!is_file($ledger)) {
            $report->check(str_contains($state, 'P3 Status: NOT STARTED / NOT AUTHORIZED'), 'Project state does not preserve P3 authorization boundary.');
            return;
        }
        $contents = file_get_contents($ledger);
        $authorized = is_string($contents) && (str_contains($contents, 'authorization: QMDB-P3-OPEN-B01') || str_contains($contents, 'authorization: QMDB-P3-B02-EXEC') || str_contains($contents, 'authorization: QMDB-P3-B03-EXEC') || str_contains($contents, 'authorization: QMDB-P3-B04-EXEC'));
        $report->check($authorized, 'P3 extension ledger is invalid.');
        $report->check(is_string($contents) && str_contains($contents, 'allowed_modules:'), 'P3 extension ledger does not constrain the allowed modules.');
        $report->check(is_string($contents) && str_contains($contents, 'P3 composition bridge may update only'), 'P3 extension ledger lacks P2 integrity assurance.');
        $report->check(str_contains($state, 'P3 Status: IN PROGRESS'), 'Project state does not record the authorized P3 transition.');
    }
}
