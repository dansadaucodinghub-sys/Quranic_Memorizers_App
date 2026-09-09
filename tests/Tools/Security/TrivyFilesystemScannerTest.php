<?php

declare(strict_types=1);

namespace Qmdb\Tests\Tools\Security;

use PHPUnit\Framework\TestCase;
use Qmdb\Tools\Security\TrivyDatabaseException;
use Qmdb\Tools\Security\TrivyFilesystemScanner;
use Qmdb\Tools\Support\ProcessResult;

final class TrivyFilesystemScannerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = rtrim(sys_get_temp_dir(), '\\/') . '/qmdb trivy scanner ' . bin2hex(random_bytes(8));
        mkdir($this->root . '/.build/security-tools/bin', 0700, true);
        mkdir($this->root . '/var/cache/trivy/db', 0700, true);
        mkdir($this->root . '/target with spaces', 0700, true);
        file_put_contents($this->root . '/.build/security-tools/bin/trivy' . (PHP_OS_FAMILY === 'Windows' ? '.exe' : ''), 'binary');
        file_put_contents($this->root . '/var/cache/trivy/db/trivy.db', 'database');
        file_put_contents($this->root . '/var/cache/trivy/db/metadata.json', json_encode([
            'Version' => 2,
            'UpdatedAt' => gmdate(DATE_ATOM),
            'NextUpdate' => gmdate(DATE_ATOM, time() + 86400),
            'DownloadedAt' => gmdate(DATE_ATOM),
        ], JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);
    }

    public function testFreshCacheScanUsesSkipUpdateAndPassesOnlyAValidEmptyReport(): void
    {
        $command = [];
        $scanner = new TrivyFilesystemScanner(runner: function (array $received) use (&$command): ProcessResult {
            $command = $received;
            $report = $this->reportPath($received);
            file_put_contents($report, json_encode(['Results' => []], JSON_THROW_ON_ERROR));
            return new ProcessResult($received, 0, '', '');
        });

        $result = $scanner->scan($this->root, $this->root . '/target with spaces', 'artifact');

        self::assertSame('pass', $result['status']);
        self::assertContains('--cache-dir', $command);
        self::assertContains('--skip-db-update', $command);
        self::assertSame($this->root . '/target with spaces', $this->lastArgument($command));
    }

    public function testHighCriticalFindingsFailClosed(): void
    {
        $scanner = new TrivyFilesystemScanner(runner: static function (array $command): ProcessResult {
            $index = array_search('--output', $command, true);
            if (!is_int($index) || !isset($command[$index + 1])) {
                throw new \RuntimeException('Trivy report argument is unavailable.');
            }
            $report = $command[$index + 1];
            file_put_contents($report, json_encode(['Results' => [[
                'Vulnerabilities' => [['Severity' => 'CRITICAL']],
            ]]], JSON_THROW_ON_ERROR));
            return new ProcessResult($command, 1, '', '');
        });

        $this->expectException(TrivyDatabaseException::class);
        $this->expectExceptionCode(TrivyDatabaseException::FINDINGS);
        $scanner->scan($this->root, $this->root . '/target with spaces', 'artifact');
    }

    public function testMissingOrMalformedScanReportFailsClosed(): void
    {
        $scanner = new TrivyFilesystemScanner(runner: static fn (array $command): ProcessResult => new ProcessResult($command, 0, '', ''));

        $this->expectException(TrivyDatabaseException::class);
        $this->expectExceptionCode(TrivyDatabaseException::REPORT_INVALID);
        $scanner->scan($this->root, $this->root . '/target with spaces', 'artifact');
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            if (!$item instanceof \SplFileInfo) {
                continue;
            }
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($path);
    }

    /** @param list<string> $command */
    private function reportPath(array $command): string
    {
        $index = array_search('--output', $command, true);
        if (!is_int($index) || !isset($command[$index + 1])) {
            throw new \RuntimeException('Trivy report argument is unavailable.');
        }
        return $command[$index + 1];
    }

    /** @param list<string> $command */
    private function lastArgument(array $command): string
    {
        if ($command === []) {
            throw new \RuntimeException('Trivy command is empty.');
        }
        return $command[count($command) - 1];
    }
}
