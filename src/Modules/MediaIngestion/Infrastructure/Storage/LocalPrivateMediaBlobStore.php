<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaIngestion\Infrastructure\Storage;

use Qmdb\Modules\MediaIngestion\Application\MediaBlobStore;
use Qmdb\Modules\MediaIngestion\Application\MediaStagingCleaner;

/** Development/test store outside public/ with immutable object writes. */
final readonly class LocalPrivateMediaBlobStore implements MediaBlobStore, MediaStagingCleaner
{
    public function __construct(private string $root)
    {
        if ($root === '' || str_contains($root, "\0")) {
            throw new \InvalidArgumentException('Media root is invalid.');
        }
    }
    public function putImmutable(string $objectKey, string $contents): void
    {
        if (!$this->valid($objectKey) || $contents === '') {
            throw new \InvalidArgumentException('Media object is invalid.');
        }
        $path = $this->path($objectKey);
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Media directory cannot be created.');
        }
        $this->assertContained($path);
        $temporary = $directory . DIRECTORY_SEPARATOR . '.writing-' . bin2hex(random_bytes(16));
        $file = @fopen($temporary, 'x+b');
        if ($file === false) {
            throw new \RuntimeException('Private media staging could not be created.');
        }
        try {
            if (!flock($file, LOCK_EX)) {
                throw new \RuntimeException('Media object lock failed.');
            }
            $offset = 0;
            while ($offset < strlen($contents)) {
                $written = fwrite($file, substr($contents, $offset, 65_536));
                if ($written === false || $written === 0) {
                    throw new \RuntimeException('Media object cannot be stored.');
                }
                $offset += $written;
            }
            if (!fflush($file)) {
                throw new \RuntimeException('Media object flush failed.');
            }
            @chmod($temporary, 0600);
            // Hard-link promotion is atomic and never replaces an existing object (also on NTFS).
            if (!@link($temporary, $path)) {
                $old = $this->get($objectKey);
                if (!hash_equals(hash('sha256', $old, true), hash('sha256', $contents, true))) {
                    throw new \DomainException('Media object key is immutable.');
                }
            }
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
    public function get(string $objectKey): string
    {
        if (!$this->valid($objectKey)) {
            throw new \InvalidArgumentException('Media object key is invalid.');
        }
        $path = $this->path($objectKey);
        $this->assertContained($path);
        $file = @fopen($path, 'rb');
        if ($file === false) {
            throw new \RuntimeException('Media object is unavailable.');
        }
        try {
            if (!flock($file, LOCK_SH)) {
                throw new \RuntimeException('Media object lock failed.');
            }
            $contents = stream_get_contents($file);
            if (!is_string($contents)) {
                throw new \RuntimeException('Media object is unavailable.');
            }
            return $contents;
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }
    }
    private function assertContained(string $path): void
    {
        $root = realpath($this->root);
        $directory = realpath(dirname($path));
        if ($root === false || $directory === false || is_link($path)) {
            throw new \RuntimeException('Media storage boundary is unavailable.');
        }
        $prefix = rtrim(str_replace('\\', '/', $root), '/') . '/';
        $candidate = rtrim(str_replace('\\', '/', $directory), '/') . '/';
        if (DIRECTORY_SEPARATOR === '\\') {
            $prefix = strtolower($prefix);
            $candidate = strtolower($candidate);
        }
        if (!str_starts_with($candidate, $prefix)) {
            throw new \RuntimeException('Media storage boundary is invalid.');
        }
    }
    public function removeStaging(string $objectKey, string $expectedSha256): bool
    {
        if (!$this->valid($objectKey) || !str_starts_with($objectKey, 'staging/') || strlen($expectedSha256) !== 32) {
            throw new \InvalidArgumentException('Only a checksummed staging object may be removed.');
        }
        $path = $this->path($objectKey);
        // A prior attempt may have removed the bytes before its SQL transaction failed.
        if (!file_exists($path) && !is_link($path)) {
            return false;
        }
        $this->assertContained($path);
        if (!hash_equals($expectedSha256, hash('sha256', $this->get($objectKey), true))) {
            throw new \DomainException('Staging checksum differs; retain the evidence.');
        }
        if (!unlink($path)) {
            throw new \RuntimeException('Expired staging object could not be removed.');
        }
        return true;
    }
    private function path(string $key): string
    {
        return rtrim($this->root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $key);
    }
    private function valid(string $key): bool
    {
        return preg_match('~\A(?:staging|quarantine|private|variants)/[a-z0-9][a-z0-9._/-]{2,300}\z~', $key) === 1 && !str_contains($key, '..');
    }
}
