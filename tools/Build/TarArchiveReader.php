<?php

declare(strict_types=1);

namespace Qmdb\Tools\Build;

final class TarArchiveReader
{
    /** @return array<string, array{type: string, mode: int, content: string}> */
    public function entries(string $archive): array
    {
        $gzip = file_get_contents($archive);
        $tar = is_string($gzip) ? gzdecode($gzip) : false;
        if (!is_string($tar)) {
            throw new \RuntimeException('Release archive is not valid gzip data.');
        }
        $entries = [];
        $offset = 0;
        $length = strlen($tar);
        while ($offset + 512 <= $length) {
            $header = substr($tar, $offset, 512);
            $offset += 512;
            if ($header === str_repeat("\0", 512)) {
                break;
            }
            $name = rtrim(substr($header, 0, 100), "\0");
            $prefix = rtrim(substr($header, 345, 155), "\0");
            $path = $prefix === '' ? $name : $prefix . '/' . $name;
            $type = substr($header, 156, 1);
            $mode = octdec(trim(substr($header, 100, 8), "\0 "));
            $size = octdec(trim(substr($header, 124, 12), "\0 "));
            if (!is_int($mode) || !is_int($size)) {
                throw new \RuntimeException('Release archive numeric metadata exceeds platform limits.');
            }
            $this->assertSafePath($path);
            if (!in_array($type, ["\0", '0', '5'], true)) {
                throw new \RuntimeException('Release archive contains prohibited entry type: ' . $path);
            }
            if (isset($entries[$path])) {
                throw new \RuntimeException('Release archive contains duplicate path: ' . $path);
            }
            $content = $type === '5' ? '' : substr($tar, $offset, $size);
            if (strlen($content) !== $size) {
                throw new \RuntimeException('Release archive entry is truncated: ' . $path);
            }
            $entries[$path] = ['type' => $type === '5' ? 'directory' : 'file', 'mode' => $mode, 'content' => $content];
            $offset += intdiv($size + 511, 512) * 512;
        }
        ksort($entries, SORT_STRING);
        return $entries;
    }

    /** @param array<string, array{type: string, mode: int, content: string}> $entries */
    public function extract(array $entries, string $destination): void
    {
        if (!is_dir($destination) && !mkdir($destination, 0700, true) && !is_dir($destination)) {
            throw new \RuntimeException('Unable to create extraction directory.');
        }
        foreach ($entries as $path => $entry) {
            $target = $destination . '/' . rtrim($path, '/');
            if ($entry['type'] === 'directory') {
                if (!is_dir($target) && !mkdir($target, 0755, true) && !is_dir($target)) {
                    throw new \RuntimeException('Unable to create extracted directory.');
                }
                continue;
            }
            $directory = dirname($target);
            if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new \RuntimeException('Unable to create extracted parent directory.');
            }
            if (file_put_contents($target, $entry['content'], LOCK_EX) === false) {
                throw new \RuntimeException('Unable to extract archive file: ' . $path);
            }
            chmod($target, $entry['mode']);
        }
    }

    private function assertSafePath(string $path): void
    {
        if (
            $path === '' || str_contains($path, '\\') || str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:/', $path) === 1
            || in_array('..', explode('/', trim($path, '/')), true)
        ) {
            throw new \RuntimeException('Release archive contains unsafe path.');
        }
    }
}
