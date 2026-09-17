<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaProcessing\Infrastructure\Security;

use Qmdb\Modules\MediaProcessing\Application\MediaScanner;
use Qmdb\Modules\MediaProcessing\Application\MediaScannerHealthCheck;

/** ClamAV adapter. It never interpolates media data into a shell command. */
final readonly class ClamAvMediaScanner implements MediaScanner, MediaScannerHealthCheck
{
    public function __construct(private string $binary, private int $timeoutSeconds = 30)
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
            $result = $this->run([$this->binary, '--no-summary', '--', $file]);
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
        $result = $this->run([$this->binary, '--version']);
        return ['healthy' => $result['exit'] === 0, 'engine' => 'CLAMAV', 'safe_code' => $result['exit'] === 0 ? 'READY' : 'VERSION_FAILED'];
    }

    /** @param list<string> $command @return array{exit:int} */
    private function run(array $command): array
    {
        $pipes = [];
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            return ['exit' => 2];
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $deadline = microtime(true) + $this->timeoutSeconds;
        while (proc_get_status($process)['running'] && microtime(true) < $deadline) {
            usleep(10_000);
        }
        if (proc_get_status($process)['running']) {
            proc_terminate($process);
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        return ['exit' => proc_close($process)];
    }
}
