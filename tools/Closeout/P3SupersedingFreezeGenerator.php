<?php

declare(strict_types=1);

namespace Qmdb\Tools\Closeout;

use Qmdb\Tools\Support\GitMetadata;

final readonly class P3SupersedingFreezeGenerator
{
    public function __construct(private P3SupersedingFreezePolicy $policy = new P3SupersedingFreezePolicy())
    {
    }

    /** @return array{path:string,files:int,sha256:string,status:string} */
    public function generate(string $root): array
    {
        $git = GitMetadata::inspect($root);
        if ($git->sourceState !== 'clean') {
            throw new \RuntimeException('P3 superseding freeze generation requires a clean committed source tree.');
        }
        $entries = $this->policy->entries($root);
        $path = $root . '/' . P3SupersedingFreezePolicy::MANIFEST;
        if (file_put_contents($path, $this->render($entries, $git), LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write P3 superseding freeze manifest.');
        }
        $hash = hash_file('sha256', $path);
        if (!is_string($hash)) {
            throw new \RuntimeException('Unable to hash P3 superseding freeze manifest.');
        }
        return ['path' => $path, 'files' => count($entries), 'sha256' => $hash, 'status' => 'FROZEN'];
    }

    /** @param list<array{path:string,category:string,sha256:string}> $entries */
    public function render(array $entries, GitMetadata $git): string
    {
        $lines = [
            'p3_superseding_freeze:',
            '  project: "Qur\'an Memorizer DB"',
            '  project_code: QMDB',
            '  freeze_id: QMDB-P3-FRZ-002',
            '  supersedes: QMDB-P3-FRZ-001',
            '  supersession_scope: "post-P3 additive extension governance only"',
            '  phase: P3',
            '  phase_title: "People, Geography, Organizations, and Participation Foundation"',
            '  status: FROZEN',
            '  historical_manifest: docs/closeout/p3/qmdb-p3-people-geography-organizations-participation-freeze.yaml',
            '  extension_ledger: ' . PostP3ExtensionLedger::PATH,
            '  extension_ledger_genesis_sha256: ' . (new PostP3ExtensionLedger())->genesisHash(),
            '  generated_at_utc: "' . gmdate('Y-m-d\\TH:i:s\\Z', $git->sourceDateEpoch) . '"',
            '  source_revision: "' . $git->revision . '"',
            '  source_state: clean',
            '  governed_files:',
            '    count: ' . count($entries),
            '    manifest_sha256: ' . hash('sha256', implode("\n", array_map(static fn (array $entry): string => $entry['path'] . ':' . $entry['sha256'], $entries)) . "\n"),
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
