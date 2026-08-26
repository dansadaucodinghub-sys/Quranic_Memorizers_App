<?php

declare(strict_types=1);

namespace Qmdb\Tools\Support;

final class ProcessRunner
{
    /**
     * @param non-empty-list<string> $command
     * @param array<string, string>|null $environment
     */
    public function run(array $command, ?string $workingDirectory = null, ?array $environment = null): ProcessResult
    {
        foreach ($command as $argument) {
            if (str_contains($argument, "\0")) {
                throw new \InvalidArgumentException('Process arguments must not contain null bytes.');
            }
        }

        $command = $this->normalizeWindowsCommand($command);

        $stdoutPath = tempnam(sys_get_temp_dir(), 'qmdb-process-out-');
        $stderrPath = tempnam(sys_get_temp_dir(), 'qmdb-process-err-');
        if (!is_string($stdoutPath) || !is_string($stderrPath)) {
            throw new \RuntimeException('Unable to allocate process output files.');
        }
        try {
            $pipes = [];
            $process = proc_open(
                $command,
                [
                    0 => ['pipe', 'r'],
                    1 => ['file', $stdoutPath, 'w'],
                    2 => ['file', $stderrPath, 'w'],
                ],
                $pipes,
                $workingDirectory,
                $environment,
                ['bypass_shell' => true],
            );
            if (!is_resource($process)) {
                throw new \RuntimeException('Unable to start process: ' . self::display($command));
            }
            fclose($pipes[0]);
            $exitCode = proc_close($process);
            $stdoutValue = file_get_contents($stdoutPath);
            $stderrValue = file_get_contents($stderrPath);
            $stdout = is_string($stdoutValue) ? $stdoutValue : '';
            $stderr = is_string($stderrValue) ? $stderrValue : '';
        } finally {
            @unlink($stdoutPath);
            @unlink($stderrPath);
        }

        return new ProcessResult(
            $command,
            $exitCode,
            $stdout,
            $stderr,
        );
    }

    /** @param non-empty-list<string> $command
     *  @return non-empty-list<string>
     */
    private function normalizeWindowsCommand(array $command): array
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return $command;
        }
        $executable = strtolower($command[0]);
        if ($executable === 'composer') {
            $launcher = $this->findOnPath('composer.bat');
            $phar = $launcher === null ? null : dirname($launcher) . '/composer.phar';
            if ($phar !== null && is_file($phar)) {
                return array_merge([PHP_BINARY, $phar], array_slice($command, 1));
            }
        }
        if ($executable === 'npm') {
            $node = $this->findOnPath('node.exe');
            $cli = $this->findNpmCliOnPath();
            if ($node !== null && $cli !== null && is_file($cli)) {
                return array_merge([$node, $cli], array_slice($command, 1));
            }
        }
        return $command;
    }

    private function findOnPath(string $filename): ?string
    {
        $path = getenv('PATH');
        if (!is_string($path)) {
            return null;
        }
        foreach (explode(PATH_SEPARATOR, $path) as $directory) {
            if (trim($directory) === '') {
                continue;
            }
            $candidate = rtrim($directory, "\\/") . '/' . $filename;
            if (is_file($candidate)) {
                return str_replace('\\', '/', $candidate);
            }
        }
        return null;
    }

    private function findNpmCliOnPath(): ?string
    {
        $path = getenv('PATH');
        if (!is_string($path)) {
            return null;
        }
        foreach (explode(PATH_SEPARATOR, $path) as $directory) {
            if (trim($directory) === '') {
                continue;
            }
            $candidate = rtrim($directory, "\\/") . '/node_modules/npm/bin/npm-cli.js';
            if (is_file($candidate)) {
                return str_replace('\\', '/', $candidate);
            }
        }
        return null;
    }

    /** @param list<string> $command */
    public static function display(array $command): string
    {
        return implode(' ', array_map(
            static fn (string $argument): string => preg_match('/^[A-Za-z0-9_:\/.=-]+$/', $argument) === 1
                ? $argument
                : escapeshellarg($argument),
            $command,
        ));
    }
}
