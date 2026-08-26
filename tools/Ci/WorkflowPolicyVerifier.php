<?php

declare(strict_types=1);

namespace Qmdb\Tools\Ci;

use Qmdb\Tools\Support\ProcessRunner;

final class WorkflowPolicyVerifier
{
    /** @var list<string> */
    private const WORKFLOWS = ['.github/workflows/ci.yml', '.github/workflows/release-artifact.yml'];

    public function __construct(
        private readonly string $root,
        private readonly ProcessRunner $runner = new ProcessRunner(),
    ) {
    }

    public function verify(): VerificationReport
    {
        $report = new VerificationReport();
        foreach (self::WORKFLOWS as $relative) {
            $path = $this->root . '/' . $relative;
            $report->check(is_file($path), 'Required workflow is missing: ' . $relative);
            if (!is_file($path)) {
                continue;
            }
            $yaml = file_get_contents($path);
            if (!is_string($yaml)) {
                $report->check(false, 'Workflow is unreadable: ' . $relative);
                continue;
            }
            $this->verifyWorkflow($relative, $yaml, $report);
        }

        $actionlint = $this->findExecutable('actionlint');
        if ($actionlint === null) {
            $report->warning('actionlint is unavailable locally; hosted CI must install the pinned binary.');
        } else {
            $result = $this->runner->run([$actionlint, '-no-color'], $this->root);
            $report->check($result->exitCode === 0, 'actionlint failed: ' . $result->output());
        }
        return $report;
    }

    private function verifyWorkflow(string $relative, string $yaml, VerificationReport $report): void
    {
        $report->check(!str_contains($yaml, 'pull_request_target'), $relative . ' must not use pull_request_target.');
        $report->check(!str_contains($yaml, 'write-all'), $relative . ' must not grant write-all.');
        $report->check(
            preg_match('/(?m)^permissions:\R\s+contents:\s+read\s*$/', $yaml) === 1,
            $relative . ' must declare read-only contents permission.',
        );
        $report->check(preg_match('/(?m)^concurrency:\R/', $yaml) === 1, $relative . ' must define concurrency.');
        $report->check(!str_contains($yaml, 'continue-on-error: true'), $relative . ' must fail closed.');
        $report->check(!str_contains($yaml, '${{ secrets.'), $relative . ' must not require repository secrets.');
        $report->check(
            preg_match('/\$\{\{\s*github\.event\.pull_request\.(?:title|head\.ref|body)/', $yaml) !== 1,
            $relative . ' directly interpolates untrusted pull-request data.',
        );
        $report->check(
            preg_match('/\b(?:kubectl|helm|terraform|ansible|gh\s+release|docker\s+push)\b/i', $yaml) !== 1,
            $relative . ' contains a deployment or release-publication command.',
        );
        preg_match_all('/(?m)^\s*uses:\s*([^\s#]+)(?:\s+#.*)?$/', $yaml, $uses);
        foreach ($uses[1] as $reference) {
            if (str_starts_with($reference, './')) {
                continue;
            }
            $report->check(
                preg_match('/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+@[a-f0-9]{40}$/', $reference) === 1,
                $relative . ' contains an unpinned action: ' . $reference,
            );
        }
        if (str_contains($yaml, 'actions/checkout@')) {
            $report->check(
                preg_match('/persist-credentials:\s*false/', $yaml) === 1,
                $relative . ' must disable persisted checkout credentials.',
            );
            $report->check(preg_match('/fetch-depth:\s*0/', $yaml) === 1, $relative . ' must fetch full history.');
        }
        preg_match_all('/image:\s*mysql:([^\s]+)/', $yaml, $images);
        foreach ($images[1] as $tag) {
            $report->check(
                preg_match('/^\d+\.\d+\.\d+$/', $tag) === 1,
                $relative . ' must pin MySQL to an exact patch version.',
            );
        }
        if ($relative === '.github/workflows/release-artifact.yml') {
            $verifyPosition = strpos($yaml, 'php tools/build/verify-release.php');
            $uploadPosition = strpos($yaml, 'actions/upload-artifact@');
            $report->check(
                $verifyPosition !== false && $uploadPosition !== false && $verifyPosition < $uploadPosition,
                'Release artifact upload must occur only after release verification.',
            );
        }
    }

    private function findExecutable(string $name): ?string
    {
        $local = $this->root . '/.build/security-tools/bin/' . $name;
        if (PHP_OS_FAMILY === 'Windows' && is_file($local . '.exe')) {
            return $local . '.exe';
        }
        if (is_file($local)) {
            return $local;
        }
        $probe = $this->runner->run(PHP_OS_FAMILY === 'Windows' ? ['where.exe', $name] : ['which', $name], $this->root);
        if ($probe->exitCode !== 0) {
            return null;
        }
        $path = strtok(trim($probe->stdout), "\r\n");
        return is_string($path) ? $path : null;
    }
}
