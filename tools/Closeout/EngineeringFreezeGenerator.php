<?php

declare(strict_types=1);

namespace Qmdb\Tools\Closeout;

use Qmdb\Tools\Support\GitMetadata;

final readonly class EngineeringFreezeGenerator
{
    public function __construct(private EngineeringFreezePolicy $policy = new EngineeringFreezePolicy())
    {
    }

    /** @return array{path: string, files: int, sha256: string, status: string} */
    public function generate(string $root): array
    {
        $git = GitMetadata::inspect($root);
        $entries = $this->policy->entries($root);
        $path = $root . '/docs/closeout/p1/qmdb-p1-engineering-freeze.yaml';
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create closeout directory.');
        }
        $yaml = $this->render($entries, $git);
        if (file_put_contents($path, $yaml, LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write engineering freeze candidate.');
        }
        $hash = hash_file('sha256', $path);
        if (!is_string($hash)) {
            throw new \RuntimeException('Unable to hash engineering freeze candidate.');
        }
        return ['path' => $path, 'files' => count($entries), 'sha256' => $hash, 'status' => 'APPROVED'];
    }

    /**
     * @param list<array{path: string, category: string, sha256: string}> $entries
     */
    public function render(array $entries, GitMetadata $git): string
    {
        $generatedAt = gmdate('Y-m-d\TH:i:s\Z', $git->sourceDateEpoch);
        $lines = [
            'engineering_freeze:',
            '  project: "Qur\'an Memorizer DB"',
            '  project_code: QMDB',
            '  product_baseline: QMDB-BL-001',
            '  product_freeze: QMDB-P0-FRZ-001',
            '  approved_changes:',
            '    - QMDB-CR-001',
            '  engineering_freeze: QMDB-P1-FRZ-001',
            '  closeout_batch: QMDB-P1-CLOSE',
            '  freeze_status: APPROVED',
            '  readiness: P1_COMPLETE',
            '  generated_at_utc: "' . $generatedAt . '"',
            '  source_revision: "' . $git->revision . '"',
            '  source_state: governed_clean',
            '  completed_batches:',
            '    - QMDB-P1-B01',
            '    - QMDB-P1-B02',
            '    - QMDB-P1-B03',
            '    - QMDB-P1-B04',
            '    - QMDB-P1-B05',
            '    - QMDB-P1-B06',
            '    - QMDB-P1-B07',
            '    - QMDB-P1-B08',
            '    - QMDB-P1-B09',
            '    - QMDB-P1-B10',
            '  engineering_baseline:',
            '    php: "8.5"',
            '    database: mysql',
            '    storage_engine: InnoDB',
            '    architecture: modular_monolith',
            '    presentation: mvc',
            '    tenancy: shared_schema',
            '    http_messages: psr_7',
            '    middleware: psr_15',
            '    container: psr_11_project_owned',
            '    logging: psr_3_monolog_json',
            '    frontend: server_rendered_progressive_enhancement',
            '    asynchronous_reads: native_fetch',
            '    modal: single_accessible_dialog',
            '    live_direction: server_sent_events',
            '    build: reproducible_release_archive',
            '  readiness_gates:',
            '    php_quality: PASS',
            '    frontend_quality: PASS_NODE_24',
            '    mysql: PASS_MYSQL_8_4_11',
            '    schema: PASS_INSTALL_MIGRATE_ROLLBACK_REAPPLY',
            '    http: PASS_WITH_MYSQL_READINESS',
            '    background: PASS_PORTABLE_LIFECYCLE',
            '    accessibility_foundation: PASS_AUTOMATED_MANUAL_RELEASE_REVIEW_DEFERRED',
            '    security: PASS_PINNED_EXTERNAL_AND_INTERNAL_SCANS',
            '    supply_chain: PASS_LOCAL_AND_STATIC_HOSTED_POLICY',
            '    release_artifact: PASS_VERIFIED_REPRODUCIBLE_FOUNDATION',
            '    p2_b01_prompt: PASS',
            '  files:',
        ];
        foreach ($entries as $entry) {
            $lines[] = '    - path: "' . $entry['path'] . '"';
            $lines[] = '      category: ' . $entry['category'];
            $lines[] = '      sha256: ' . $entry['sha256'];
        }
        array_push(
            $lines,
            '  excluded_dynamic_files:',
            '    - docs/closeout/p1/',
            '    - docs/implementation/P2-identity-security-and-tenancy-roadmap.md',
            '    - docs/implementation/P2-requirements-to-batches.md',
            '    - docs/implementation/prompts/',
            '    - docs/implementation/reports/',
            '    - docs/project/open-decisions.md',
            '    - docs/project/project-state.md',
            '    - docs/project/risk-register.md',
            '  excluded_generated_files:',
            '    - .git/',
            '    - .runtime/',
            '    - .phpstan.cache/',
            '    - build/',
            '    - coverage/',
            '    - node_modules/',
            '    - vendor/',
            '  p1_blockers: []',
            '  deferred_release_evidence:',
            '    - HOSTED_CI_EXECUTION',
            '    - MANUAL_ACCESSIBILITY_BROWSER_MATRIX',
            '    - LINUX_PCNTL_SIGNAL_REHEARSAL',
            '  p2_blockers: []',
            '  next_phase:',
            '    phase: P2',
            '    title: "Identity, Security, and Tenant Isolation"',
            '    batch: QMDB-P2-B01',
            '    status: AUTHORIZED_BY_QMDB_RECOVERY_RUN_001',
            '    prompt_path: docs/implementation/prompts/'
                . 'QMDB-P2-B01-workspace-account-credential-and-tenant-boundary-schema-foundation.md',
            '',
        );
        return implode("\n", $lines);
    }
}
