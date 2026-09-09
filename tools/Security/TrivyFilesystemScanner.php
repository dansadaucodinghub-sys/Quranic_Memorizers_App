<?php

declare(strict_types=1);

namespace Qmdb\Tools\Security;

use Qmdb\Tools\Support\ProcessResult;
use Qmdb\Tools\Support\ProcessRunner;

final class TrivyFilesystemScanner
{
    /** @var \Closure(list<string>, string): ProcessResult */
    private \Closure $run;

    /** @param callable(list<string>, string): ProcessResult|null $runner */
    public function __construct(
        private readonly TrivyDatabaseCache $database = new TrivyDatabaseCache(),
        ?callable $runner = null,
    ) {
        $this->run = $runner === null
            ? static fn (array $command, string $directory): ProcessResult => self::runNative($command, $directory)
            : \Closure::fromCallable($runner);
    }

    /** @return array{status:string,report:string,database:array{version:int,updated_at:string,next_update:string,downloaded_at:string}} */
    public function scan(string $sourceRoot, string $target, string $mode): array
    {
        if (!is_dir($target) || !is_readable($target)) {
            throw new TrivyDatabaseException('Trivy scan target is unavailable.', TrivyDatabaseException::ARTIFACT_INVALID);
        }
        if (preg_match('/\A[a-z0-9][a-z0-9-]*\z/', $mode) !== 1) {
            throw new TrivyDatabaseException('Trivy scan mode is invalid.', TrivyDatabaseException::POLICY_INVALID);
        }
        $suffix = PHP_OS_FAMILY === 'Windows' ? '.exe' : '';
        $binary = $sourceRoot . '/.build/security-tools/bin/trivy' . $suffix;
        $reportRoot = $sourceRoot . '/build/reports';
        if (!is_dir($reportRoot) && !mkdir($reportRoot, 0755, true) && !is_dir($reportRoot)) {
            throw new TrivyDatabaseException('Trivy report directory is unavailable.', TrivyDatabaseException::POLICY_INVALID);
        }
        $database = $this->database->ensureCurrent($binary, $sourceRoot . '/var/cache/trivy');
        $report = $reportRoot . '/trivy-' . $mode . '.json';
        @unlink($report);
        $result = ($this->run)([
            $binary,
            'filesystem',
            '--cache-dir',
            $sourceRoot . '/var/cache/trivy',
            '--skip-db-update',
            '--scanners',
            'vuln,secret,misconfig',
            '--severity',
            'HIGH,CRITICAL',
            '--exit-code',
            '1',
            '--timeout',
            '15m',
            '--format',
            'json',
            '--output',
            $report,
            '--skip-dirs',
            '.git',
            '--skip-dirs',
            '.runtime',
            '--skip-dirs',
            '.build',
            '--skip-dirs',
            '.phpstan.cache',
            '--skip-dirs',
            '.phpunit.cache',
            '--skip-dirs',
            'node_modules',
            '--skip-dirs',
            'build',
            $target,
        ], $sourceRoot);
        $findings = $this->highCriticalFindings($report);
        if ($findings > 0 || $result->exitCode === TrivyDatabaseException::FINDINGS) {
            if ($findings === 0) {
                throw new TrivyDatabaseException('Trivy returned a findings exit code without a valid HIGH/CRITICAL report.', TrivyDatabaseException::REPORT_INVALID);
            }
            throw new TrivyDatabaseException('Trivy found ' . $findings . ' HIGH/CRITICAL finding(s).', TrivyDatabaseException::FINDINGS);
        }
        if ($result->exitCode !== 0) {
            throw new TrivyDatabaseException('Trivy execution failed: ' . $this->summary($result->output()), TrivyDatabaseException::EXECUTION_FAILURE);
        }

        return ['status' => 'pass', 'report' => $report, 'database' => $database];
    }

    private function highCriticalFindings(string $report): int
    {
        if (!is_file($report) || !is_readable($report)) {
            throw new TrivyDatabaseException('Trivy report is missing or invalid.', TrivyDatabaseException::REPORT_INVALID);
        }
        $raw = file_get_contents($report);
        try {
            $decoded = is_string($raw) ? json_decode($raw, true, 512, JSON_THROW_ON_ERROR) : null;
        } catch (\JsonException) {
            $decoded = null;
        }
        if (!is_array($decoded) || !isset($decoded['Results']) || !is_array($decoded['Results'])) {
            throw new TrivyDatabaseException('Trivy report is missing or invalid.', TrivyDatabaseException::REPORT_INVALID);
        }
        $count = 0;
        foreach ($decoded['Results'] as $result) {
            if (!is_array($result)) {
                throw new TrivyDatabaseException('Trivy report has an invalid result.', TrivyDatabaseException::REPORT_INVALID);
            }
            foreach (['Vulnerabilities', 'Misconfigurations', 'Secrets'] as $key) {
                $entries = $result[$key] ?? [];
                if (!is_array($entries)) {
                    throw new TrivyDatabaseException('Trivy report has invalid ' . $key . '.', TrivyDatabaseException::REPORT_INVALID);
                }
                foreach ($entries as $entry) {
                    if (!is_array($entry)) {
                        throw new TrivyDatabaseException('Trivy report has an invalid finding.', TrivyDatabaseException::REPORT_INVALID);
                    }
                    $severity = $entry['Severity'] ?? null;
                    if (is_string($severity) && in_array(strtoupper($severity), ['HIGH', 'CRITICAL'], true)) {
                        ++$count;
                    }
                }
            }
        }
        return $count;
    }

    private function summary(string $output): string
    {
        $output = preg_replace('/\s+/', ' ', trim($output)) ?? '';
        return substr($output, 0, 300);
    }

    /** @param array<mixed> $command */
    private static function runNative(array $command, string $directory): ProcessResult
    {
        $normalized = [];
        foreach ($command as $argument) {
            if (!is_string($argument)) {
                throw new \InvalidArgumentException('Trivy command contains an invalid argument.');
            }
            $normalized[] = $argument;
        }
        if ($normalized === []) {
            throw new \InvalidArgumentException('Trivy command is empty.');
        }
        return (new ProcessRunner())->run($normalized, $directory);
    }
}
