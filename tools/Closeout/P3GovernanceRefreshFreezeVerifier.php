<?php

declare(strict_types=1);

namespace Qmdb\Tools\Closeout;

use Qmdb\Tools\Ci\VerificationReport;
use Qmdb\Tools\Support\ProcessRunner;

final readonly class P3GovernanceRefreshFreezeVerifier
{
    public function __construct(
        private P3GovernanceRefreshFreezePolicy $policy = new P3GovernanceRefreshFreezePolicy(),
        private PostP3ExtensionLedger $ledger = new PostP3ExtensionLedger(),
        private P3HistoricalFreezeVerifier $historical = new P3HistoricalFreezeVerifier(),
    ) {
    }

    public function verify(string $root): VerificationReport
    {
        $report = new VerificationReport();
        $yaml = file_get_contents($root . '/' . P3GovernanceRefreshFreezePolicy::MANIFEST);
        if (!is_string($yaml)) {
            $report->check(false, 'P3 governance-refresh freeze manifest is unreadable.');
            return $report;
        }
        foreach (['freeze_id: QMDB-P3-FRZ-003', 'supersedes: QMDB-P3-FRZ-002', 'phase: P3', 'source_state: clean'] as $identity) {
            $report->check(str_contains($yaml, $identity), 'P3 governance-refresh identity is missing: ' . $identity);
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
        preg_match_all('/^    - path: "([^"]+)"\R\s+category: [A-Z0-9_]+\R\s+sha256: ([a-f0-9]{64})$/m', $yaml, $matches, PREG_SET_ORDER);
        $baseline = [];
        foreach ($matches as $match) {
            $baseline[$match[1]] = $match[2];
        }
        $report->check(count($baseline) > 1_000, 'P3 governance-refresh inventory is incomplete.');
        $current = [];
        foreach ($this->policy->entries($root) as $entry) {
            $current[$entry['path']] = $entry['sha256'];
        }
        foreach ($baseline as $path => $hash) {
            $report->check(isset($current[$path]), 'P3 governance-refresh file was deleted: ' . $path);
            if (isset($current[$path]) && $current[$path] !== $hash) {
                $authorization = $modified[$path] ?? null;
                $report->check($authorization !== null && $authorization['previous_sha256'] === $hash && $authorization['sha256'] === $current[$path], 'P3 governance-refresh file changed without authorized extension: ' . $path);
            }
        }
        foreach ($current as $path => $hash) {
            if (!isset($baseline[$path])) {
                $authorization = $modified[$path] ?? null;
                $report->check(
                    ($newFiles[$path] ?? null) === $hash
                    || ($authorization !== null && ($newFiles[$path] ?? null) === $authorization['previous_sha256'] && $authorization['sha256'] === $hash),
                    'Unknown post-P3 governed file: ' . $path,
                );
            }
        }
        foreach ($newFiles as $path => $hash) {
            $authorization = $modified[$path] ?? null;
            $report->check(
                isset($current[$path]) && !isset($baseline[$path])
                && ($current[$path] === $hash || ($authorization !== null && $authorization['previous_sha256'] === $hash && $authorization['sha256'] === $current[$path])),
                'Authorized post-P3 file is absent or altered: ' . $path,
            );
        }
        foreach ($modified as $path => $authorization) {
            $report->check((isset($baseline[$path]) || isset($newFiles[$path])) && ($current[$path] ?? null) === $authorization['sha256'], 'Authorized extension point is absent or altered: ' . $path);
        }
        $report->merge($this->historical->verify($root));
        $status = (new ProcessRunner())->run(['git', 'status', '--porcelain=v1', '--untracked-files=all'], $root);
        $report->check($status->exitCode === 0 && trim($status->stdout) === '', 'P3 governance-refresh verification requires a clean working tree.');
        return $report;
    }
}
