<?php

declare(strict_types=1);

namespace Qmdb\Tools\Closeout;

use Qmdb\Tools\Ci\VerificationReport;
use Qmdb\Tools\Support\ProcessRunner;

final readonly class P3SupersedingFreezeVerifier
{
    public function __construct(
        private P3SupersedingFreezePolicy $policy = new P3SupersedingFreezePolicy(),
        private PostP3ExtensionLedger $ledger = new PostP3ExtensionLedger(),
        private P3HistoricalFreezeVerifier $historical = new P3HistoricalFreezeVerifier(),
    ) {
    }

    public function verify(string $root): VerificationReport
    {
        $report = new VerificationReport();
        $yaml = file_get_contents($root . '/' . P3SupersedingFreezePolicy::MANIFEST);
        if (!is_string($yaml)) {
            $report->check(false, 'P3 superseding freeze manifest is unreadable.');
            return $report;
        }
        foreach (['freeze_id: QMDB-P3-FRZ-002', 'supersedes: QMDB-P3-FRZ-001', 'phase: P3', 'source_state: clean'] as $identity) {
            $report->check(str_contains($yaml, $identity), 'P3 superseding freeze identity is missing: ' . $identity);
        }
        try {
            $ledger = $this->ledger->load($root);
            $this->ledger->verify($ledger);
            $newFiles = $this->ledger->authorizedNewFiles($ledger);
            $modified = $this->ledger->authorizedModifications($ledger);
        } catch (\Throwable $exception) {
            $report->check(false, $exception->getMessage());
            return $report;
        }
        $report->check(str_contains($yaml, 'extension_ledger_genesis_sha256: ' . $this->ledger->genesisHash()), 'P3 superseding freeze does not bind the ledger genesis.');
        preg_match_all('/^    - path: "([^"]+)"\R\s+category: [A-Z0-9_]+\R\s+sha256: ([a-f0-9]{64})$/m', $yaml, $matches, PREG_SET_ORDER);
        $baseline = [];
        foreach ($matches as $match) {
            $baseline[$match[1]] = $match[2];
        }
        $report->check(count($baseline) > 1_000, 'P3 superseding freeze inventory is incomplete.');
        $current = [];
        foreach ($this->policy->entries($root) as $entry) {
            $current[$entry['path']] = $entry['sha256'];
        }
        foreach ($baseline as $path => $hash) {
            $report->check(isset($current[$path]), 'P3 effective baseline file was deleted: ' . $path);
            if (!isset($current[$path])) {
                continue;
            }
            if ($current[$path] !== $hash) {
                $authorization = $modified[$path] ?? null;
                $report->check(
                    $authorization !== null && $authorization['previous_sha256'] === $hash && $authorization['sha256'] === $current[$path],
                    'P3 effective baseline file was modified without authorized append-only extension: ' . $path,
                );
            }
        }
        foreach ($current as $path => $hash) {
            if (isset($baseline[$path])) {
                continue;
            }
            $report->check(($newFiles[$path] ?? null) === $hash, 'Unknown post-P3 governed file: ' . $path);
        }
        foreach ($newFiles as $path => $hash) {
            $report->check(isset($current[$path]) && !isset($baseline[$path]) && $current[$path] === $hash, 'Authorized post-P3 file is absent or altered: ' . $path);
        }
        foreach ($modified as $path => $authorization) {
            $report->check(isset($baseline[$path]) && ($current[$path] ?? null) === $authorization['sha256'], 'Authorized extension point is absent or altered: ' . $path);
        }
        $history = $this->historical->verify($root);
        $report->merge($history);
        $runner = new ProcessRunner();
        $status = $runner->run(['git', 'status', '--porcelain=v1', '--untracked-files=all'], $root);
        $report->check($status->exitCode === 0 && trim($status->stdout) === '', 'P3 effective freeze verification requires a clean working tree.');
        return $report;
    }
}
