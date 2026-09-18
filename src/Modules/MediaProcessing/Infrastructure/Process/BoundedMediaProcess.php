<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaProcessing\Infrastructure\Process;

/** Shell-free execution with bounded capture, including on Windows without nonblocking pipes. */
final class BoundedMediaProcess
{
    /** @param non-empty-list<string> $command
     * @return array{exit:int,stdout:string}
     */
    public static function run(array $command, int $timeoutSeconds): array
    {
        if ($timeoutSeconds < 1 || $timeoutSeconds > 600) {
            throw new \InvalidArgumentException('Invalid media process timeout.');
        }
        $directory = sys_get_temp_dir() . '/qmdb-media-capture-' . bin2hex(random_bytes(16));
        if (!mkdir($directory, 0700)) {
            throw new \RuntimeException('Media process capture could not be created.');
        }
        $stdout = $directory . '/stdout';
        $stderr = $directory . '/stderr';
        $process = null;
        $deadline = microtime(true) + $timeoutSeconds;
        $exit = -1;
        try {
            $pipes = [];
            $process = proc_open($command, [
                0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'rb'],
                1 => ['file', $stdout, 'wb'],
                2 => ['file', $stderr, 'wb'],
            ], $pipes, null, null, ['bypass_shell' => true]);
            if (!is_resource($process)) {
                throw new \RuntimeException('Media process did not start.');
            }
            do {
                $state = proc_get_status($process);
                clearstatcache(true, $stdout);
                clearstatcache(true, $stderr);
                $outputSize = filesize($stdout);
                $errorSize = filesize($stderr);
                if ($outputSize === false || $errorSize === false || $outputSize + $errorSize > 1_048_576) {
                    throw new \RuntimeException('Media process output exceeded its limit.');
                }
                if (!$state['running']) {
                    $exit = $state['exitcode'];
                    break;
                }
                if (microtime(true) >= $deadline) {
                    throw new \RuntimeException('Media process deadline exceeded.');
                }
                usleep(10_000);
            } while (true);
            $closedExit = proc_close($process);
            $process = null;
            if ($exit < 0) {
                $exit = $closedExit;
            }
            $output = file_get_contents($stdout, false, null, 0, 1_048_577);
            if (!is_string($output) || strlen($output) > 1_048_576) {
                throw new \RuntimeException('Media process output could not be read safely.');
            }
            return ['exit' => $exit, 'stdout' => $output];
        } finally {
            if (is_resource($process)) {
                if (proc_get_status($process)['running']) {
                    proc_terminate($process, 9);
                }
                proc_close($process);
            }
            foreach ([$stdout, $stderr] as $capture) {
                if (is_file($capture)) {
                    unlink($capture);
                }
            }
            rmdir($directory);
        }
    }
}
