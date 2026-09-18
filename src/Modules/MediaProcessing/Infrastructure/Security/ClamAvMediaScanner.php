<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaProcessing\Infrastructure\Security;

use Qmdb\Modules\MediaProcessing\Application\MediaScanner;
use Qmdb\Modules\MediaProcessing\Application\MediaScannerHealthCheck;

/** ClamAV adapter. It never interpolates media data into a shell command. */
final readonly class ClamAvMediaScanner implements MediaScanner, MediaScannerHealthCheck
{
    public function __construct(private string $binary, private int $timeoutSeconds = 30, private ?string $databaseDirectory = null)
    {
        if ($binary === '' || str_contains($binary, "\0") || $timeoutSeconds < 1 || $timeoutSeconds > 300) {
            throw new \InvalidArgumentException('Scanner configuration is invalid.');
        }
    }

    public function scan(string $contents): array
    {
        if ($contents === '') {
            return ['clean' => false, 'engine' => 'CLAMAV', 'safe_code' => 'EMPTY_CONTENT'];
        }
        $file = tempnam(sys_get_temp_dir(), 'qmdb-media-scan-');
        if ($file === false) {
            return ['clean' => false, 'engine' => 'CLAMAV', 'safe_code' => 'TEMPORARY_STORAGE_UNAVAILABLE'];
        }
        try {
            if (file_put_contents($file, $contents, LOCK_EX) !== strlen($contents)) {
                return ['clean' => false, 'engine' => 'CLAMAV', 'safe_code' => 'TEMPORARY_STORAGE_UNAVAILABLE'];
            }
            $result = $this->run([$this->binary, ...$this->databaseArguments(), '--no-summary', '--', $file]);
            return match ($result['exit']) {
                0 => ['clean' => true, 'engine' => 'CLAMAV', 'safe_code' => 'CLEAN'],
                1 => ['clean' => false, 'engine' => 'CLAMAV', 'safe_code' => 'INFECTED'],
                default => ['clean' => false, 'engine' => 'CLAMAV', 'safe_code' => 'SCANNER_ERROR'],
            };
        } finally {
            @unlink($file);
        }
    }

    public function check(): array
    {
        // Windows does not reliably implement is_executable(); an absolute regular file is
        // still executed through proc_open with bypass_shell and fixed arguments below.
        if (!is_file($this->binary)) {
            return ['healthy' => false, 'engine' => 'CLAMAV', 'safe_code' => 'BINARY_UNAVAILABLE'];
        }
        try {
            $result = \Qmdb\Modules\MediaProcessing\Infrastructure\Process\BoundedMediaProcess::run([$this->binary, ...$this->databaseArguments(), '--version'], $this->timeoutSeconds);
            if ($result['exit'] !== 0 || preg_match('~\AClamAV [0-9.]+/[0-9]+/(.+)~', trim($result['stdout']), $matches) !== 1) {
                return ['healthy' => false, 'engine' => 'CLAMAV', 'safe_code' => 'DATABASE_UNAVAILABLE'];
            }
            $date = new \DateTimeImmutable($matches[1]);
            $age = time() - $date->getTimestamp();
            $healthy = $age >= -86400 && $age <= 604800 && $this->scan('QMDB harmless scanner readiness sample')['clean'];
            return ['healthy' => $healthy, 'engine' => 'CLAMAV', 'safe_code' => $healthy ? 'READY' : 'DATABASE_STALE_OR_SCAN_FAILED'];
        } catch (\Throwable) {
            return ['healthy' => false, 'engine' => 'CLAMAV', 'safe_code' => 'HEALTH_CHECK_FAILED'];
        }
    }
    /** @return list<string> */
    private function databaseArguments(): array
    {
        if ($this->databaseDirectory === null) {
            return [];
        }
        // ClamAV's Windows database loader rejects mixed path separators even though
        // PHP and proc_open accept them. Resolve the operator-configured directory.
        $directory = realpath($this->databaseDirectory);
        if ($directory === false || !is_dir($directory)) {
            throw new \RuntimeException('Configured scanner database directory is unavailable.');
        }
        return ['--database=' . $directory];
    }

    /**
     * @param non-empty-list<string> $command
     * @return array{exit:int}
     */
    private function run(array $command): array
    {
        try {
            $result = \Qmdb\Modules\MediaProcessing\Infrastructure\Process\BoundedMediaProcess::run($command, $this->timeoutSeconds);
            return ['exit' => $result['exit']];
        } catch (\RuntimeException) {
            return ['exit' => 2];
        }
    }
}
