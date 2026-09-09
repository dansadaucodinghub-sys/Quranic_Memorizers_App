<?php

declare(strict_types=1);

namespace Qmdb\Tools\Ci;

final class FrozenBaselineVerifier
{
    private const P3_B02_DOCUMENTATION_EXTENSION_LEDGER = 'docs/project/p3-p0-freeze-extension-ledger.yaml';
    private const P3_B05_DOCUMENTATION_EXTENSION_LEDGER = 'docs/project/p3-p0-b05-freeze-extension-ledger.yaml';
    private const P3_B06_DOCUMENTATION_EXTENSION_LEDGER = 'docs/project/p3-p0-b06-freeze-extension-ledger.yaml';
    private const P4_GOVERNANCE_DOCUMENTATION_EXTENSION_LEDGER = 'docs/project/p4-p0-governance-freeze-extension-ledger.yaml';

    /** @var list<string> */
    private const P3_B02_DOCUMENTATION_PATHS = [
        'docs/accessibility/accessibility-verification-matrix.md',
        'docs/data/02-domain-aggregate-and-ownership-model.md',
        'docs/data/05-table-and-column-data-dictionary.md',
        'docs/data/06-relationship-constraint-and-integrity-catalog.md',
        'docs/data/07-indexing-and-query-access-patterns.md',
        'docs/data/08-record-versioning-lifecycle-and-deletion.md',
        'docs/data/10-data-classification-ownership-and-lineage.md',
        'docs/data/mysql-logical-schema.yaml',
        'docs/implementation/phase-and-batch-roadmap.md',
        'docs/operations/quality-attribute-parameter-register.md',
        'docs/privacy/data-classification-and-handling.md',
        'docs/project/decision-register.md',
        'docs/security/security-control-catalog.md',
        'docs/security/security-verification-matrix.md',
        'docs/security/threat-model.md',
    ];

    /** @var list<string> */
    private const P3_B05_DOCUMENTATION_PATHS = self::P3_B02_DOCUMENTATION_PATHS;

    /** @var list<string> */
    private const P3_B06_DOCUMENTATION_PATHS = [
        'docs/implementation/phase-and-batch-roadmap.md',
    ];

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
        $baselineHashes = [];
        foreach ($matches as $match) {
            $baselineHashes[$match[1]] = $match[2];
        }
        $approvedExtensions = $this->approvedP3B06DocumentationExtensions(
            $this->approvedP3B05DocumentationExtensions(
                $this->approvedP3B02DocumentationExtensions($baselineHashes, $report),
                $report,
            ),
            $report,
        );
        $approvedExtensions = $this->approvedP4GovernanceDocumentationExtensions($approvedExtensions, $report);
        $paths = [];
        foreach ($matches as $match) {
            $relative = $match[1];
            $paths[] = $relative;
            $absolute = $this->root . '/' . $relative;
            $report->check(is_file($absolute), 'Frozen file is missing: ' . $relative);
            if (is_file($absolute)) {
                $expectedHash = $approvedExtensions[$relative] ?? $match[2];
                $report->check(
                    hash_file('sha256', $absolute) === $expectedHash,
                    'Frozen checksum mismatch: ' . $relative,
                );
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

    /**
     * @param array<string, string> $baselineHashes
     * @return array<string, string>
     */
    private function approvedP3B02DocumentationExtensions(array $baselineHashes, VerificationReport $report): array
    {
        $ledgerPath = $this->root . '/' . self::P3_B02_DOCUMENTATION_EXTENSION_LEDGER;
        if (!is_file($ledgerPath)) {
            return [];
        }
        $ledger = file_get_contents($ledgerPath);
        if (!is_string($ledger)) {
            $report->check(false, 'P3 B02 P0 documentation-extension ledger is unreadable.');

            return [];
        }
        foreach (
            [
                'ledger_id: QMDB-P3-P0-EXT-001',
                'authorization: QMDB-P3-B02-EXEC',
                'baseline_id: QMDB-P0-FRZ-001',
                'preserves_historical_baseline: true',
            ] as $required
        ) {
            $report->check(str_contains($ledger, $required), 'P3 B02 P0 extension ledger is missing: ' . $required);
        }
        preg_match_all(
            '/^\s+- path: "([^"]+)"\R\s+baseline_sha256: ([a-f0-9]{64})\R\s+extension_sha256: ([a-f0-9]{64})\R\s+reason: "([^"]+)"\s*$/m',
            $ledger,
            $matches,
            PREG_SET_ORDER,
        );
        $report->check(
            count($matches) === count(self::P3_B02_DOCUMENTATION_PATHS),
            'P3 B02 P0 extension ledger must contain every approved documentation extension exactly once.',
        );

        $paths = [];
        $approved = [];
        foreach ($matches as $match) {
            $path = $match[1];
            $paths[] = $path;
            $baselineHash = $baselineHashes[$path] ?? null;
            $matchesBaseline = is_string($baselineHash) && hash_equals($baselineHash, $match[2]);
            $report->check($matchesBaseline, 'P3 B02 P0 extension has an unrecognized baseline hash: ' . $path);
            if ($matchesBaseline) {
                $approved[$path] = $match[3];
            }
        }
        $report->check(
            $paths === self::P3_B02_DOCUMENTATION_PATHS,
            'P3 B02 P0 extension paths must be the ordered, approved documentation list.',
        );
        $report->check(
            count(array_unique($paths)) === count($paths),
            'P3 B02 P0 extension paths must be unique.',
        );

        return $approved;
    }

    /**
     * @param array<string, string> $previousExtensions
     * @return array<string, string>
     */
    private function approvedP3B05DocumentationExtensions(array $previousExtensions, VerificationReport $report): array
    {
        $ledgerPath = $this->root . '/' . self::P3_B05_DOCUMENTATION_EXTENSION_LEDGER;
        if (!is_file($ledgerPath)) {
            return $previousExtensions;
        }
        $ledger = file_get_contents($ledgerPath);
        if (!is_string($ledger)) {
            $report->check(false, 'P3 B05 P0 documentation-extension ledger is unreadable.');

            return $previousExtensions;
        }
        foreach (
            [
                'ledger_id: QMDB-P3-P0-EXT-002',
                'authorization: QMDB-P3-B05-EXEC',
                'baseline_id: QMDB-P0-FRZ-001',
                'preserves_historical_baseline: true',
            ] as $required
        ) {
            $report->check(str_contains($ledger, $required), 'P3 B05 P0 extension ledger is missing: ' . $required);
        }
        preg_match_all(
            '/^    - path: "([^"]+)"\R\s+previous_extension_sha256: ([a-f0-9]{64})\R\s+extension_sha256: ([a-f0-9]{64})\R\s+reason: "([^"]+)"\s*$/m',
            $ledger,
            $matches,
            PREG_SET_ORDER,
        );
        $report->check(
            count($matches) === count(self::P3_B05_DOCUMENTATION_PATHS),
            'P3 B05 P0 extension ledger must contain every approved documentation extension exactly once.',
        );

        $paths = [];
        $approved = $previousExtensions;
        foreach ($matches as $match) {
            $path = $match[1];
            $paths[] = $path;
            $previousHash = $previousExtensions[$path] ?? null;
            $matchesPrevious = is_string($previousHash) && hash_equals($previousHash, $match[2]);
            $report->check($matchesPrevious, 'P3 B05 P0 extension has an unrecognized previous hash: ' . $path);
            if ($matchesPrevious) {
                $approved[$path] = $match[3];
            }
        }
        $report->check(
            $paths === self::P3_B05_DOCUMENTATION_PATHS,
            'P3 B05 P0 extension paths must be the ordered, approved documentation list.',
        );
        $report->check(
            count(array_unique($paths)) === count($paths),
            'P3 B05 P0 extension paths must be unique.',
        );

        return $approved;
    }

    /**
     * @param array<string, string> $previousExtensions
     * @return array<string, string>
     */
    private function approvedP3B06DocumentationExtensions(array $previousExtensions, VerificationReport $report): array
    {
        $ledgerPath = $this->root . '/' . self::P3_B06_DOCUMENTATION_EXTENSION_LEDGER;
        if (!is_file($ledgerPath)) {
            return $previousExtensions;
        }
        $ledger = file_get_contents($ledgerPath);
        if (!is_string($ledger)) {
            $report->check(false, 'P3 B06 P0 documentation-extension ledger is unreadable.');

            return $previousExtensions;
        }
        foreach (['ledger_id: QMDB-P3-P0-EXT-003', 'authorization: QMDB-P3-B06-EXEC', 'baseline_id: QMDB-P0-FRZ-001', 'preserves_historical_baseline: true'] as $required) {
            $report->check(str_contains($ledger, $required), 'P3 B06 P0 extension ledger is missing: ' . $required);
        }
        preg_match_all('/^    - path: "([^"]+)"\R\s+previous_extension_sha256: ([a-f0-9]{64})\R\s+extension_sha256: ([a-f0-9]{64})\R\s+reason: "([^"]+)"\s*$/m', $ledger, $matches, PREG_SET_ORDER);
        $report->check(count($matches) === count(self::P3_B06_DOCUMENTATION_PATHS), 'P3 B06 P0 extension ledger must contain every approved documentation extension exactly once.');
        $paths = [];
        $approved = $previousExtensions;
        foreach ($matches as $match) {
            $path = $match[1];
            $paths[] = $path;
            $previousHash = $previousExtensions[$path] ?? null;
            $matchesPrevious = is_string($previousHash) && hash_equals($previousHash, $match[2]);
            $report->check($matchesPrevious, 'P3 B06 P0 extension has an unrecognized previous hash: ' . $path);
            if ($matchesPrevious) {
                $approved[$path] = $match[3];
            }
        }
        $report->check($paths === self::P3_B06_DOCUMENTATION_PATHS, 'P3 B06 P0 extension paths must be the ordered, approved documentation list.');
        $report->check(count(array_unique($paths)) === count($paths), 'P3 B06 P0 extension paths must be unique.');

        return $approved;
    }

    /**
     * @param array<string, string> $previousExtensions
     * @return array<string, string>
     */
    private function approvedP4GovernanceDocumentationExtensions(array $previousExtensions, VerificationReport $report): array
    {
        $ledger = file_get_contents($this->root . '/' . self::P4_GOVERNANCE_DOCUMENTATION_EXTENSION_LEDGER);
        if (!is_string($ledger)) {
            $report->check(false, 'P4 governance P0 extension ledger is unreadable.');
            return $previousExtensions;
        }
        foreach (['ledger_id: QMDB-P4-P0-GOV-EXT-001', 'authorization: QMDB-CR-002', 'baseline_id: QMDB-P0-FRZ-001', 'preserves_historical_baseline: true'] as $required) {
            $report->check(str_contains($ledger, $required), 'P4 governance P0 extension ledger is missing: ' . $required);
        }
        preg_match_all('/^    - path: "([^"]+)"\R\s+previous_extension_sha256: ([a-f0-9]{64})\R\s+extension_sha256: ([a-f0-9]{64})\R\s+reason: "([^\"]+)"\s*$/m', $ledger, $matches, PREG_SET_ORDER);
        $report->check(count($matches) === 1, 'P4 governance P0 extension ledger must contain exactly one entry.');
        $approved = $previousExtensions;
        if (count($matches) !== 1) {
            return $approved;
        }
        $entry = $matches[0];
        $report->check($entry[1] === 'docs/project/decision-register.md', 'P4 governance P0 extension path is not approved.');
        $previous = $previousExtensions[$entry[1]] ?? null;
        $report->check(is_string($previous) && hash_equals($previous, $entry[2]), 'P4 governance P0 extension has an unrecognized previous hash.');
        if (is_string($previous) && hash_equals($previous, $entry[2])) {
            $approved[$entry[1]] = $entry[3];
        }
        return $approved;
    }
}
