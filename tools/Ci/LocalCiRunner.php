<?php

declare(strict_types=1);

namespace Qmdb\Tools\Ci;

use Qmdb\Tools\Support\JsonFile;
use Qmdb\Tools\Support\ProcessResult;
use Qmdb\Tools\Support\ProcessRunner;

final class LocalCiRunner
{
    /** @var list<array<string, mixed>> */
    private array $results = [];

    public function __construct(
        private readonly string $root,
        private readonly ProcessRunner $runner = new ProcessRunner(),
    ) {
    }

    public function run(bool $hosted = false): int
    {
        $stages = [
            ['repository-policy', ['php', 'tools/ci/verify-repository.php']],
            ['frozen-baseline', ['php', 'tools/ci/verify-frozen-baseline.php']],
            ['workflow-policy', ['php', 'tools/ci/verify-workflows.php']],
            ['markdown-links', ['php', 'tools/ci/verify-markdown-links.php']],
            ['lockfiles', ['php', 'tools/ci/verify-lockfiles.php']],
            ['composer-install', ['composer', 'install', '--no-interaction', '--prefer-dist', '--no-progress']],
            ['composer-validate', ['composer', 'validate', '--strict', '--no-check-publish']],
            ['composer-audit', ['composer', 'audit', '--locked']],
            ['composer-autoload', ['composer', 'dump-autoload', '--strict-psr']],
            ['composer-platform', ['composer', 'check-platform-reqs']],
            ['php-syntax', ['php', 'tools/ci/verify-php-syntax.php']],
            ['coding-style', ['composer', 'cs:check']],
            ['static-analysis', ['composer', 'analyse']],
            ['php-tests', ['composer', 'test']],
            ['frontend-install', ['npm', 'ci', '--ignore-scripts']],
            ['frontend-quality', ['npm', 'run', 'quality']],
        ];

        if ($this->mysqlConfigured()) {
            $stages[] = ['mysql-tests', ['composer', 'test:mysql']];
        } else {
            $this->recordSkipped('mysql-tests', 'QMDB_TEST_DB_* is not configured.');
        }

        foreach ($stages as [$name, $command]) {
            if (!$this->execute($name, $command)) {
                return $this->finish(1);
            }
        }

        $securityStages = $this->securityStages();
        foreach ($securityStages as [$name, $command]) {
            if (!$hosted && !$this->securityToolAvailable($name)) {
                $this->recordSkipped($name, 'Pinned Linux security binary is unavailable on this host.');
                continue;
            }
            if (!$this->execute($name, $command)) {
                return $this->finish(1);
            }
        }

        foreach (
            [
                ['sbom-generate', ['php', 'tools/sbom/generate-production-sbom.php']],
                ['sbom-validate', ['php', 'tools/sbom/validate-sbom.php']],
                ['licences-generate', ['php', 'tools/sbom/generate-runtime-licences.php']],
                ['release-build', ['php', 'tools/build/build-release.php']],
                ['release-verify', ['php', 'tools/build/verify-release.php']],
                ['git-diff-check', ['git', 'diff', '--check']],
                ['frozen-baseline-recheck', ['php', 'tools/ci/verify-frozen-baseline.php']],
            ] as [$name, $command]
        ) {
            if (!$this->execute($name, $command)) {
                return $this->finish(1);
            }
        }

        return $this->finish(0);
    }

    /** @param non-empty-list<string> $command */
    private function execute(string $name, array $command): bool
    {
        fwrite(STDOUT, sprintf("[%s] %s\n", $name, ProcessRunner::display($command)));
        $started = microtime(true);
        try {
            $result = $this->runner->run($command, $this->root);
        } catch (\Throwable $exception) {
            $result = new ProcessResult($command, 127, '', $exception->getMessage());
        }
        $this->results[] = [
            'name' => $name,
            'command' => $command,
            'exit_code' => $result->exitCode,
            'status' => $result->exitCode === 0 ? 'passed' : 'failed',
            'duration_seconds' => round(microtime(true) - $started, 3),
            'output' => $this->redact($result->output()),
        ];
        if ($result->output() !== '') {
            fwrite($result->exitCode === 0 ? STDOUT : STDERR, $result->output());
        }
        return $result->exitCode === 0;
    }

    private function recordSkipped(string $name, string $reason): void
    {
        $this->results[] = [
            'name' => $name,
            'command' => [],
            'exit_code' => null,
            'status' => 'skipped',
            'duration_seconds' => 0,
            'output' => $reason,
        ];
        fwrite(STDOUT, sprintf("[%s] SKIPPED: %s\n", $name, $reason));
    }

    private function finish(int $exitCode): int
    {
        $directory = $this->root . '/build/reports';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            fwrite(STDERR, "Unable to create local CI report directory.\n");
            return 1;
        }
        JsonFile::writeObject($directory . '/local-ci.json', [
            'schema_version' => 1,
            'status' => $exitCode === 0 ? 'passed' : 'failed',
            'host' => PHP_OS_FAMILY,
            'php_version' => PHP_VERSION,
            'steps' => $this->results,
        ]);
        printf("Local CI: %s (%d recorded stages)\n", $exitCode === 0 ? 'PASS' : 'FAIL', count($this->results));
        return $exitCode;
    }

    private function mysqlConfigured(): bool
    {
        $required = [
            'QMDB_TEST_DB_HOST',
            'QMDB_TEST_DB_NAME',
            'QMDB_TEST_DB_USERNAME',
            'QMDB_TEST_DB_PASSWORD',
        ];
        foreach ($required as $name) {
            if (!is_string(getenv($name)) || getenv($name) === '') {
                return false;
            }
        }
        return true;
    }

    private function securityToolAvailable(string $stage): bool
    {
        $binary = match ($stage) {
            'secret-scan' => 'gitleaks',
            'filesystem-scan' => 'trivy',
            'shellcheck' => 'shellcheck',
            default => throw new \InvalidArgumentException('Unknown security stage: ' . $stage),
        };
        $suffix = PHP_OS_FAMILY === 'Windows' ? '.exe' : '';
        return is_file($this->root . '/.build/security-tools/bin/' . $binary . $suffix);
    }

    /** @return list<array{string, non-empty-list<string>}> */
    private function securityStages(): array
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return [
                ['secret-scan', [
                    'powershell.exe',
                    '-NoProfile',
                    '-ExecutionPolicy',
                    'Bypass',
                    '-File',
                    'tools/windows/run-qmdb-secret-scan.ps1',
                    'repository',
                ]],
                ['filesystem-scan', [
                    'powershell.exe',
                    '-NoProfile',
                    '-ExecutionPolicy',
                    'Bypass',
                    '-File',
                    'tools/windows/run-qmdb-filesystem-scan.ps1',
                    'repository',
                ]],
            ];
        }

        return [
            ['secret-scan', ['bash', 'tools/security/run-secret-scan.sh', 'repository']],
            ['filesystem-scan', ['bash', 'tools/security/run-filesystem-scan.sh', 'repository']],
            ['shellcheck', [
                $this->root . '/.build/security-tools/bin/shellcheck',
                'tools/security/install-tools.sh',
                'tools/security/run-secret-scan.sh',
                'tools/security/run-filesystem-scan.sh',
            ]],
        ];
    }

    private function redact(string $output): string
    {
        return preg_replace(
            [
                '/-----BEGIN (?:RSA |EC |OPENSSH |DSA )?PRIVATE KEY-----'
                    . '[\s\S]*?-----END (?:RSA |EC |OPENSSH |DSA )?PRIVATE KEY-----/',
                '/\b(?:gh[pousr]_|sk-(?:proj-)?|npm_|xox[baprs]-)[A-Za-z0-9_-]{10,}\b/',
            ],
            '[REDACTED]',
            $output,
        ) ?? '[OUTPUT REDACTION FAILED]';
    }
}
