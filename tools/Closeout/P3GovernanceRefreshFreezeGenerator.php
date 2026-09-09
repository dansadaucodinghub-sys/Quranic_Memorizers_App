<?php

declare(strict_types=1);

namespace Qmdb\Tools\Closeout;

use Qmdb\Tools\Support\GitMetadata;

final readonly class P3GovernanceRefreshFreezeGenerator
{
    public function __construct(private P3GovernanceRefreshFreezePolicy $policy = new P3GovernanceRefreshFreezePolicy())
    {
    }

    /** @return array{path:string,files:int,sha256:string,status:string} */
    public function generate(string $root): array
    {
        $git = GitMetadata::inspect($root);
        if ($git->sourceState !== 'clean') {
            throw new \RuntimeException('P3 governance refresh requires a clean committed source tree.');
        }
        $entries = $this->policy->entries($root);
        $path = $root . '/' . P3GovernanceRefreshFreezePolicy::MANIFEST;
        if (file_put_contents($path, $this->render($entries, $git), LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write P3 governance refresh manifest.');
        }
        $hash = hash_file('sha256', $path);
        if (!is_string($hash)) {
            throw new \RuntimeException('Unable to hash P3 governance refresh manifest.');
        }
        return ['path' => $path, 'files' => count($entries), 'sha256' => $hash, 'status' => 'FROZEN'];
    }

    /** @param list<array{path:string,category:string,sha256:string}> $entries */
    private function render(array $entries, GitMetadata $git): string
    {
        $lines = [
            'p3_superseding_freeze:', '  project: "Qur\'an Memorizer DB"', '  project_code: QMDB',
            '  freeze_id: QMDB-P3-FRZ-003', '  supersedes: QMDB-P3-FRZ-002',
            '  supersession_scope: "P0 controlled-extension reconciliation only"', '  phase: P3',
            '  status: FROZEN', '  extension_ledger: ' . PostP3ExtensionLedger::PATH,
            '  extension_ledger_genesis_sha256: ' . (new PostP3ExtensionLedger())->genesisHash(),
            '  source_revision: "' . $git->revision . '"', '  source_state: clean',
            '  governed_files:', '    count: ' . count($entries), '  files:',
        ];
        foreach ($entries as $entry) {
            $lines[] = '    - path: "' . $entry['path'] . '"';
            $lines[] = '      category: ' . $entry['category'];
            $lines[] = '      sha256: ' . $entry['sha256'];
        }
        return implode("\n", $lines) . "\n";
    }
}
