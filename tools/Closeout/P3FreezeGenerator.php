<?php

declare(strict_types=1);

namespace Qmdb\Tools\Closeout;

use Qmdb\Tools\Support\GitMetadata;

final readonly class P3FreezeGenerator
{
    public function __construct(private P3FreezePolicy $policy = new P3FreezePolicy())
    {
    }

    /** @return array{path:string,files:int,sha256:string,status:string} */
    public function generate(string $root): array
    {
        $git = GitMetadata::inspect($root);
        if ($git->sourceState !== 'clean') {
            throw new \RuntimeException('P3 freeze generation requires a clean committed source tree.');
        }
        $entries = $this->policy->entries($root);
        $path = $root . '/docs/closeout/p3/qmdb-p3-people-geography-organizations-participation-freeze.yaml';
        if (file_put_contents($path, $this->render($entries, $git), LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write P3 freeze manifest.');
        }
        $hash = hash_file('sha256', $path);
        if (!is_string($hash)) {
            throw new \RuntimeException('Unable to hash P3 freeze manifest.');
        }

        return ['path' => $path, 'files' => count($entries), 'sha256' => $hash, 'status' => 'FROZEN'];
    }

    /** @param list<array{path:string,category:string,sha256:string}> $entries */
    public function render(array $entries, GitMetadata $git): string
    {
        $lines = [
            'p3_freeze:',
            '  project: "Qur\'an Memorizer DB"',
            '  project_code: QMDB',
            '  freeze_id: QMDB-P3-FRZ-001',
            '  phase: P3',
            '  phase_title: "People, Geography, Organizations, and Participation Foundation"',
            '  closeout: QMDB-P3-CLOSE',
            '  status: FROZEN',
            '  source_product_baseline: QMDB-BL-001',
            '  frozen_product_baseline: QMDB-P0-FRZ-001',
            '  frozen_engineering_baseline: QMDB-P1-FRZ-001',
            '  frozen_identity_tenant_baseline: QMDB-P2-FRZ-001',
            '  generated_at_utc: "' . gmdate('Y-m-d\\TH:i:s\\Z', $git->sourceDateEpoch) . '"',
            '  source_revision: "' . $git->revision . '"',
            '  source_state: clean',
            '  completed_batches:',
            '    - QMDB-P3-B01', '    - QMDB-P3-B02', '    - QMDB-P3-B03',
            '    - QMDB-P3-B04', '    - QMDB-P3-B05', '    - QMDB-P3-B06',
            '  governed_files:',
            '    count: ' . count($entries),
            '    manifest_sha256: ' . hash('sha256', implode("\n", array_map(static fn (array $entry): string => $entry['path'] . ':' . $entry['category'] . ':' . $entry['sha256'], $entries)) . "\n"),
            '  files:',
        ];
        foreach ($entries as $entry) {
            $lines[] = '    - path: "' . $entry['path'] . '"';
            $lines[] = '      category: ' . $entry['category'];
            $lines[] = '      sha256: ' . $entry['sha256'];
        }

        return implode("\n", $lines) . "\n";
    }
}
