<?php

declare(strict_types=1);

namespace Qmdb\Tools\Support;

final class FileSystem
{
    /** @return list<string> */
    public static function files(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );
        $files = [];
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && ($file->isFile() || $file->isLink())) {
                $files[] = $file->getPathname();
            }
        }
        sort($files, SORT_STRING);
        return $files;
    }

    public static function relative(string $root, string $path): string
    {
        $normalizedRoot = rtrim(str_replace('\\', '/', realpath($root) ?: $root), '/');
        $normalizedPath = str_replace('\\', '/', realpath($path) ?: $path);
        $comparisonRoot = PHP_OS_FAMILY === 'Windows' ? strtolower($normalizedRoot) : $normalizedRoot;
        $comparisonPath = PHP_OS_FAMILY === 'Windows' ? strtolower($normalizedPath) : $normalizedPath;
        if (!str_starts_with($comparisonPath, $comparisonRoot . '/')) {
            throw new \InvalidArgumentException('Path is outside the expected root.');
        }
        return substr($normalizedPath, strlen($normalizedRoot) + 1);
    }

    public static function ensureInside(string $root, string $path): void
    {
        $normalizedRoot = rtrim(str_replace('\\', '/', realpath($root) ?: $root), '/');
        $normalizedPath = str_replace('\\', '/', realpath($path) ?: $path);
        $comparisonRoot = PHP_OS_FAMILY === 'Windows' ? strtolower($normalizedRoot) : $normalizedRoot;
        $comparisonPath = PHP_OS_FAMILY === 'Windows' ? strtolower($normalizedPath) : $normalizedPath;
        if (!str_starts_with($comparisonPath, $comparisonRoot . '/')) {
            throw new \RuntimeException('Refusing filesystem operation outside the project root.');
        }
    }

    public static function removeTree(string $root, string $path): void
    {
        self::ensureInside($root, $path);
        if (!file_exists($path) && !is_link($path)) {
            return;
        }
        if (is_link($path) || is_file($path)) {
            if (!unlink($path)) {
                throw new \RuntimeException('Unable to remove file: ' . $path);
            }
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
            $itemPath = $item->getPathname();
            if ($item->isLink() || $item->isFile()) {
                if (!unlink($itemPath)) {
                    throw new \RuntimeException('Unable to remove file: ' . $itemPath);
                }
            } elseif (!rmdir($itemPath)) {
                throw new \RuntimeException('Unable to remove directory: ' . $itemPath);
            }
        }
        if (!rmdir($path)) {
            throw new \RuntimeException('Unable to remove directory: ' . $path);
        }
    }

    public static function copyFile(string $source, string $destination, int $mode = 0644): void
    {
        if (is_link($source)) {
            throw new \RuntimeException('Symlinks are prohibited: ' . $source);
        }
        $directory = dirname($destination);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create directory: ' . $directory);
        }
        if (!copy($source, $destination)) {
            throw new \RuntimeException('Unable to copy file: ' . $source);
        }
        chmod($destination, $mode);
    }
}
