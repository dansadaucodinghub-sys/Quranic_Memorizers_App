<?php

declare(strict_types=1);

namespace Qmdb\Tools\Build;

use Qmdb\Tools\Policy\PathPolicy;
use Qmdb\Tools\Support\FileSystem;

final class ReleaseFilePolicy
{
    /** @var list<string> */
    private const SOURCE_FILES = ['README.md', 'bin/console', 'composer.json', 'composer.lock'];

    /** @var list<string> */
    private const SOURCE_DIRECTORIES = [
        'config', 'database/reference', 'public', 'resources/translations', 'resources/views', 'routes', 'src',
    ];

    /** @var list<string> */
    private const REQUIRED_RELEASE_FILES = [
        'README.md',
        'bin/console',
        'composer.json',
        'composer.lock',
        'database/reference/nigeria-administrative-areas-v1.json',
        'public/index.php',
        'resources/translations/en.php',
        'resources/translations/ar.php',
        'resources/views/layouts/application.php',
        'routes/web.php',
        'src/Bootstrap/ApplicationFactory.php',
        'vendor/autoload.php',
        'metadata/production-sbom.cdx.json',
        'metadata/runtime-licences.json',
        'metadata/runtime-licences.md',
        'release-manifest.json',
    ];

    /** @var list<string> */
    private const METADATA_FILES = [
        'metadata/production-sbom.cdx.json',
        'metadata/runtime-licences.json',
        'metadata/runtime-licences.md',
    ];

    public function __construct(private readonly PathPolicy $pathPolicy = new PathPolicy())
    {
    }

    /** @return list<string> */
    public function sourceFiles(string $root): array
    {
        $files = [];
        foreach (self::SOURCE_FILES as $relative) {
            if (!is_file($root . '/' . $relative)) {
                throw new \RuntimeException('Required release source is missing: ' . $relative);
            }
            $files[] = $relative;
        }
        foreach (self::SOURCE_DIRECTORIES as $relativeDirectory) {
            $directory = $root . '/' . $relativeDirectory;
            foreach (FileSystem::files($directory) as $file) {
                $files[] = FileSystem::relative($root, $file);
            }
        }
        foreach (['database/migrations.php', 'database/seeds.php'] as $relative) {
            if (!is_file($root . '/' . $relative)) {
                throw new \RuntimeException('Required release source is missing: ' . $relative);
            }
            $files[] = $relative;
        }
        foreach (['database/migrations', 'database/seeds'] as $directory) {
            foreach (FileSystem::files($root . '/' . $directory) as $file) {
                if (str_ends_with(strtolower($file), '.php')) {
                    $files[] = FileSystem::relative($root, $file);
                }
            }
        }
        $files = array_values(array_unique($files));
        sort($files, SORT_STRING);
        foreach ($files as $relative) {
            if (!$this->isAllowed($relative, false)) {
                throw new \RuntimeException('Source allowlist rejected file: ' . $relative);
            }
        }
        return $files;
    }

    public function isAllowed(string $path, bool $staged = true): bool
    {
        $path = PathPolicy::normalize($path);
        if ($this->pathPolicy->releaseViolation($path) !== null) {
            return false;
        }
        $databaseEntrypoints = ['database/migrations.php', 'database/seeds.php'];
        if (in_array($path, self::SOURCE_FILES, true) || in_array($path, $databaseEntrypoints, true)) {
            return true;
        }
        if (preg_match('#^database/(?:migrations|seeds)/[^/]+\.php$#', $path) === 1) {
            return true;
        }
        foreach (self::SOURCE_DIRECTORIES as $prefix) {
            if (str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }
        if ($staged && str_starts_with($path, 'vendor/')) {
            return true;
        }
        return $staged && ($path === 'release-manifest.json' || in_array($path, self::METADATA_FILES, true));
    }

    /** @return list<string> */
    public function requiredFiles(): array
    {
        return self::REQUIRED_RELEASE_FILES;
    }

    public function mode(string $path): int
    {
        return PathPolicy::normalize($path) === 'bin/console' ? 0755 : 0644;
    }

    public function validateArchiveFilename(string $filename): bool
    {
        return preg_match('/^qmdb-0\.1\.0-dev-[a-f0-9]{12}\.tar\.gz$/', $filename) === 1;
    }

    public function validateVersionTag(string $tag): bool
    {
        return preg_match('/^v(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)(?:-[0-9A-Za-z.-]+)?$/', $tag) === 1;
    }
}
