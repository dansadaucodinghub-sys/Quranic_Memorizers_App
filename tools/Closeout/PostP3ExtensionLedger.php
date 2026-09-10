<?php

declare(strict_types=1);

namespace Qmdb\Tools\Closeout;

/**
 * The ledger is canonical JSON stored in a .yaml file. JSON is a strict YAML
 * subset and gives this security boundary an unambiguous, dependency-free
 * parser and deterministic hashing surface.
 */
final class PostP3ExtensionLedger
{
    public const PATH = 'docs/project/post-freeze-extensions.yaml';
    public const ID = 'QMDB-P3-POST-FREEZE-EXT-001';

    /** @return array{ledger_id:string,base_freeze:string,entries:list<array<string,mixed>>} */
    public function load(string $root): array
    {
        $contents = file_get_contents($root . '/' . self::PATH);
        if (!is_string($contents)) {
            throw new \RuntimeException('Post-P3 extension ledger is unreadable.');
        }
        $ledger = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($ledger)
            || ($ledger['ledger_id'] ?? null) !== self::ID
            || ($ledger['base_freeze'] ?? null) !== 'QMDB-P3-FRZ-003'
            || !is_array($ledger['entries'] ?? null)) {
            throw new \RuntimeException('Post-P3 extension ledger has an invalid identity.');
        }

        /** @var array{ledger_id:string,base_freeze:string,entries:list<array<string,mixed>>} $ledger */
        return $ledger;
    }

    /** @param array{ledger_id:string,base_freeze:string,entries:list<array<string,mixed>>} $ledger */
    public function verify(array $ledger): void
    {
        $previous = $this->genesisHash();
        $ids = [];
        foreach ($ledger['entries'] as $entry) {
            if (!is_array($entry)
                || !is_string($entry['extension_id'] ?? null)
                || !preg_match('/^QMDB-P3-EXT-[0-9]{3}$/', $entry['extension_id'])
                || isset($ids[$entry['extension_id']])
                || !in_array($entry['authorizing_change'] ?? null, ['QMDB-CR-002', 'QMDB-CR-003'], true)
                || ($entry['base_freeze'] ?? null) !== 'QMDB-P3-FRZ-003'
                || ($entry['phase'] ?? null) !== 'P4'
                || ($entry['batch'] ?? null) !== 'QMDB-P4-B01'
                || ($entry['previous_entry_sha256'] ?? null) !== $previous
                || !is_array($entry['new_files'] ?? null)
                || !is_array($entry['modified_extension_points'] ?? null)) {
                throw new \RuntimeException('Post-P3 extension ledger entry is invalid.');
            }
            $expected = $this->entryHash($entry);
            if (!hash_equals($expected, (string) ($entry['entry_sha256'] ?? ''))) {
                throw new \RuntimeException('Post-P3 extension ledger hash chain is invalid.');
            }
            $ids[$entry['extension_id']] = true;
            $previous = $expected;
        }
    }

    public function genesisHash(): string
    {
        return hash('sha256', self::ID . "\nQMDB-P3-FRZ-003\n");
    }

    /** @param array<string,mixed> $entry */
    private function entryHash(array $entry): string
    {
        unset($entry['entry_sha256']);
        return hash('sha256', json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /** @return array<string,string> */
    public function authorizedNewFiles(array $ledger): array
    {
        $files = [];
        foreach ($ledger['entries'] as $entry) {
            foreach ($entry['new_files'] as $file) {
                if (!is_array($file) || !is_string($file['path'] ?? null) || !is_string($file['sha256'] ?? null)) {
                    throw new \RuntimeException('Post-P3 extension file entry is invalid.');
                }
                if (isset($files[$file['path']]) || !preg_match('/^[a-f0-9]{64}$/', $file['sha256'])) {
                    throw new \RuntimeException('Post-P3 extension file authorization is invalid.');
                }
                $files[$file['path']] = $file['sha256'];
            }
        }
        return $files;
    }

    /** @return array<string,array{previous_sha256:string,sha256:string}> */
    public function authorizedModifications(array $ledger): array
    {
        $paths = [];
        foreach ($ledger['entries'] as $entry) {
            foreach ($entry['modified_extension_points'] as $point) {
                if (!is_array($point)
                    || ($point['change_type'] ?? null) !== 'APPEND_ONLY'
                    || ($point['previous_entries_unchanged'] ?? null) !== true
                    || !is_string($point['path'] ?? null)
                    || !is_string($point['previous_sha256'] ?? null)
                    || !is_string($point['sha256'] ?? null)
                || !is_array($point['added_entries'] ?? null)) {
                    throw new \RuntimeException('Post-P3 extension-point authorization is invalid.');
                }
                if (isset($paths[$point['path']]) && $paths[$point['path']]['sha256'] !== $point['previous_sha256']) {
                    throw new \RuntimeException('Post-P3 extension-point sequence is invalid.');
                }
                $paths[$point['path']] = [
                    'previous_sha256' => $paths[$point['path']]['previous_sha256'] ?? $point['previous_sha256'],
                    'sha256' => $point['sha256'],
                ];
            }
        }
        return $paths;
    }
}
