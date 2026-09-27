<?php

declare(strict_types=1);

namespace Qmdb\Modules\SearchAnalytics\Infrastructure\Persistence;

final readonly class PrivateExportArtifactStore
{
    private string $root;

    public function __construct(string $projectRoot)
    {
        $this->root = rtrim($projectRoot, '\\/') . '/.runtime/p11-exports';
    }

    /** @return array{key:string,bytes:int,checksum:string} */
    public function write(string $contents, string $extension): array
    {
        if (!in_array($extension, ['csv', 'json'], true)) {
            throw new \InvalidArgumentException('Unsupported private export extension.');
        }
        if (!is_dir($this->root) && !mkdir($this->root, 0700, true) && !is_dir($this->root)) {
            throw new \RuntimeException('Private export directory could not be created.');
        }
        $key = bin2hex(random_bytes(24)) . '.' . $extension;
        $path = $this->root . '/' . $key;
        $temporary = $path . '.tmp-' . bin2hex(random_bytes(8));
        if (file_put_contents($temporary, $contents, LOCK_EX) !== strlen($contents)) {
            @unlink($temporary);
            throw new \RuntimeException('Private export artifact write failed.');
        }
        if (!rename($temporary, $path)) {
            @unlink($temporary);
            throw new \RuntimeException('Private export artifact could not be finalized.');
        }
        @chmod($path, 0600);
        return ['key' => $key, 'bytes' => strlen($contents), 'checksum' => hash('sha256', $contents, true)];
    }

    public function delete(string $key): void
    {
        if (preg_match('/\A[a-f0-9]{48}\.(csv|json)\z/', $key) !== 1) {
            throw new \InvalidArgumentException('Invalid private artifact key.');
        }
        $path = $this->root . '/' . $key;
        if (is_file($path) && !unlink($path)) {
            throw new \RuntimeException('Private export artifact deletion failed.');
        }
    }

    public function read(string $key): string
    {
        if (preg_match('/\A[a-f0-9]{48}\.(csv|json)\z/', $key) !== 1) {
            throw new \InvalidArgumentException('Invalid private artifact key.');
        }
        $path = $this->root . '/' . $key;
        $contents = is_file($path) ? file_get_contents($path) : false;
        if (!is_string($contents)) {
            throw new \DomainException('Export artifact is unavailable.');
        }
        return $contents;
    }

    public function root(): string
    {
        return $this->root;
    }
}
