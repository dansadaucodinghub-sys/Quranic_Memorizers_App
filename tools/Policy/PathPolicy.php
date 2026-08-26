<?php

declare(strict_types=1);

namespace Qmdb\Tools\Policy;

final class PathPolicy
{
    private const ALLOWED_ENV_FILES = ['.env.example'];

    /** @var list<string> */
    private const FORBIDDEN_BASENAMES = [
        '.env', 'id_rsa', 'id_ed25519', 'auth.json',
    ];

    /** @var list<string> */
    private const FORBIDDEN_EXTENSIONS = [
        'pem', 'key', 'p12', 'pfx', 'sql', 'dump', 'backup', 'sqlite', 'sqlite3', '7z', 'log',
    ];

    /** @var list<string> */
    private const FORBIDDEN_SUFFIXES = ['.sql.gz', '.tar', '.tar.gz', '.zip'];

    public function repositoryViolation(string $path): ?string
    {
        $path = self::normalize($path);
        if (in_array($path, self::ALLOWED_ENV_FILES, true)) {
            return null;
        }
        if ($this->isSensitivePath($path)) {
            return 'Forbidden or sensitive tracked file: ' . $path;
        }
        if (preg_match('#(^|/)(vendor|node_modules|build|dist|coverage)(/|$)#', $path) === 1) {
            return 'Generated or dependency directory must not be tracked: ' . $path;
        }
        return null;
    }

    public function releaseViolation(string $path): ?string
    {
        $path = self::normalize($path);
        if ($this->isSensitivePath($path)) {
            return 'Forbidden or sensitive release file: ' . $path;
        }
        if (preg_match('#(^|/)(\.git|\.github|docs|tests|tools|node_modules|build|dist|coverage)(/|$)#', $path) === 1) {
            return 'Development-only path is forbidden in releases: ' . $path;
        }
        if (preg_match('#(^|/)(phpunit\.xml|phpstan\.neon|phpcs\.xml)#', $path) === 1) {
            return 'Development configuration is forbidden in releases: ' . $path;
        }
        if (in_array($path, ['package.json', 'package-lock.json', '.npmrc', '.nvmrc', '.env.example'], true)) {
            return 'Development metadata is forbidden in releases: ' . $path;
        }
        if (preg_match('#(^|/)compose(?:\.[^/]+)?\.ya?ml$#i', $path) === 1) {
            return 'Compose configuration is forbidden in releases: ' . $path;
        }
        return null;
    }

    public function isSensitivePath(string $path): bool
    {
        $normalized = self::normalize($path);
        $basename = strtolower(basename($normalized));
        if (in_array($basename, self::FORBIDDEN_BASENAMES, true)) {
            return true;
        }
        if (str_starts_with($basename, '.env.') && $basename !== '.env.example') {
            return true;
        }
        $extension = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
        if (in_array($extension, self::FORBIDDEN_EXTENSIONS, true)) {
            return true;
        }
        foreach (self::FORBIDDEN_SUFFIXES as $suffix) {
            if (str_ends_with($basename, $suffix)) {
                return true;
            }
        }
        return false;
    }

    public static function normalize(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        while (str_starts_with($normalized, './')) {
            $normalized = substr($normalized, 2);
        }
        return ltrim($normalized, '/');
    }
}
