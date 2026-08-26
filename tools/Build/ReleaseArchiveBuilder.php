<?php

declare(strict_types=1);

namespace Qmdb\Tools\Build;

use Qmdb\Tools\Support\FileSystem;

final class ReleaseArchiveBuilder
{
    public function __construct(private readonly ReleaseFilePolicy $policy = new ReleaseFilePolicy())
    {
    }

    public function build(string $stage, string $archive, int $sourceDateEpoch): string
    {
        $files = [];
        $directories = [];
        foreach (FileSystem::files($stage) as $file) {
            $relative = FileSystem::relative($stage, $file);
            if (is_link($file)) {
                throw new \RuntimeException('Release archive rejects symlink: ' . $relative);
            }
            if (!$this->policy->isAllowed($relative)) {
                throw new \RuntimeException('Release archive rejects unexpected file: ' . $relative);
            }
            $files[$relative] = $file;
            $parts = explode('/', dirname($relative));
            $directory = '';
            foreach ($parts as $part) {
                if ($part === '.' || $part === '') {
                    continue;
                }
                $directory = $directory === '' ? $part : $directory . '/' . $part;
                $directories[$directory . '/'] = true;
            }
        }
        $entries = array_merge(array_keys($directories), array_keys($files));
        sort($entries, SORT_STRING);
        $tar = '';
        foreach ($entries as $relative) {
            if (str_ends_with($relative, '/')) {
                $tar .= $this->header($relative, 0755, 0, $sourceDateEpoch, '5');
                continue;
            }
            $contents = file_get_contents($files[$relative]);
            if (!is_string($contents)) {
                throw new \RuntimeException('Unable to read staged file: ' . $relative);
            }
            $tar .= $this->header($relative, $this->policy->mode($relative), strlen($contents), $sourceDateEpoch, '0');
            $tar .= $contents;
            $remainder = strlen($contents) % 512;
            if ($remainder !== 0) {
                $tar .= str_repeat("\0", 512 - $remainder);
            }
        }
        $tar .= str_repeat("\0", 1024);
        $gzip = gzencode($tar, 9, ZLIB_ENCODING_GZIP);
        if (!is_string($gzip)) {
            throw new \RuntimeException('Unable to gzip release archive.');
        }
        $directory = dirname($archive);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create release directory.');
        }
        if (file_put_contents($archive, $gzip, LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write release archive.');
        }
        $hash = hash_file('sha256', $archive);
        if (!is_string($hash)) {
            throw new \RuntimeException('Unable to hash release archive.');
        }
        return $hash;
    }

    private function header(string $path, int $mode, int $size, int $mtime, string $type): string
    {
        [$name, $prefix] = $this->splitPath($path);
        $header = str_pad($name, 100, "\0")
            . str_pad(sprintf('%07o', $mode), 8, "\0", STR_PAD_RIGHT)
            . str_pad(sprintf('%07o', 0), 8, "\0", STR_PAD_RIGHT)
            . str_pad(sprintf('%07o', 0), 8, "\0", STR_PAD_RIGHT)
            . str_pad(sprintf('%011o', $size), 12, "\0", STR_PAD_RIGHT)
            . str_pad(sprintf('%011o', $mtime), 12, "\0", STR_PAD_RIGHT)
            . str_repeat(' ', 8)
            . $type
            . str_repeat("\0", 100)
            . "ustar\0"
            . '00'
            . str_repeat("\0", 32)
            . str_repeat("\0", 32)
            . str_repeat("\0", 8)
            . str_repeat("\0", 8)
            . str_pad($prefix, 155, "\0")
            . str_repeat("\0", 12);
        if (strlen($header) !== 512) {
            throw new \RuntimeException('Invalid tar header length.');
        }
        $bytes = unpack('C*', $header);
        if (!is_array($bytes)) {
            throw new \RuntimeException('Unable to calculate tar header checksum.');
        }
        $checksum = array_sum($bytes);
        return substr_replace($header, sprintf('%06o', $checksum) . "\0 ", 148, 8);
    }

    /** @return array{string, string} */
    private function splitPath(string $path): array
    {
        if (strlen($path) <= 100) {
            return [$path, ''];
        }
        for ($position = strlen($path) - 1; $position > 0; $position--) {
            if ($path[$position] !== '/') {
                continue;
            }
            $prefix = substr($path, 0, $position);
            $name = substr($path, $position + 1);
            if (strlen($prefix) <= 155 && strlen($name) <= 100) {
                return [$name, $prefix];
            }
        }
        throw new \RuntimeException('Release path exceeds USTAR limits: ' . $path);
    }
}
