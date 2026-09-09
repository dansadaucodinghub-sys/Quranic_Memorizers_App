<?php

declare(strict_types=1);

namespace Qmdb\Tools\Closeout;

use Qmdb\Tools\Policy\PathPolicy;
use Qmdb\Tools\Support\FileSystem;

final class EngineeringFreezePolicy
{
    public const FROZEN = 'FROZEN_ENGINEERING_FOUNDATION';
    public const EXTENSION = 'CONTROLLED_EXTENSION_POINT';

    /** @var list<string> */
    private const ROOT_FILES = [
        '.editorconfig',
        '.env.example',
        '.gitattributes',
        '.gitignore',
        '.npmrc',
        '.nvmrc',
        'README.md',
        'compose.mysql-test.yaml',
        'composer.json',
        'composer.lock',
        'package.json',
        'package-lock.json',
        'phpcs.xml.dist',
        'phpstan.neon.dist',
        'phpunit.xml.dist',
    ];

    /** @var list<string> */
    private const SOURCE_PREFIXES = [
        '.github/',
        'bin/',
        'config/',
        'database/',
        'public/',
        'resources/',
        'routes/',
        'scripts/',
        'src/',
        'tests/',
        'tools/',
    ];

    /** @var list<string> */
    public const DYNAMIC_PATHS = [
        'docs/closeout/p1/',
        'docs/implementation/P2-identity-security-and-tenancy-roadmap.md',
        'docs/implementation/P2-requirements-to-batches.md',
        'docs/implementation/prompts/',
        'docs/implementation/reports/',
        'docs/project/open-decisions.md',
        'docs/project/project-state.md',
        'docs/project/risk-register.md',
    ];

    /** @var list<string> */
    public const GENERATED_PATHS = [
        '.git/',
        '.runtime/',
        'var/cache/',
        '.phpstan.cache/',
        'build/',
        'coverage/',
        'node_modules/',
        'vendor/',
    ];

    public function __construct(private readonly PathPolicy $pathPolicy = new PathPolicy())
    {
    }

    /** @return list<array{path: string, category: string, sha256: string}> */
    public function entries(string $root): array
    {
        $entries = [];
        foreach (FileSystem::files($root) as $absolute) {
            $path = str_replace('\\', '/', FileSystem::relative($root, $absolute));
            if (!$this->isIncluded($path)) {
                continue;
            }
            if (is_link($absolute)) {
                throw new \RuntimeException('Engineering baseline rejects symlink: ' . $path);
            }
            $violation = $this->pathPolicy->repositoryViolation($path);
            if ($violation !== null) {
                throw new \RuntimeException($violation);
            }
            $hash = hash_file('sha256', $absolute);
            if (!is_string($hash)) {
                throw new \RuntimeException('Unable to hash engineering file: ' . $path);
            }
            $entries[] = [
                'path' => $path,
                'category' => $this->category($path),
                'sha256' => $hash,
            ];
        }
        usort($entries, static fn (array $left, array $right): int => strcmp($left['path'], $right['path']));
        return $entries;
    }

    public function isIncluded(string $path): bool
    {
        $path = PathPolicy::normalize($path);
        if ($path === 'docs/closeout/p1/qmdb-p1-engineering-freeze.yaml') {
            return false;
        }
        foreach (self::DYNAMIC_PATHS as $prefix) {
            if ($path === rtrim($prefix, '/') || str_starts_with($path, $prefix)) {
                return false;
            }
        }
        foreach (self::GENERATED_PATHS as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return false;
            }
        }
        if (in_array($path, self::ROOT_FILES, true)) {
            return true;
        }
        foreach (self::SOURCE_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }
        if ($path === 'docs/project/change-requests/QMDB-CR-001-asynchronous-progressive-interaction.md') {
            return true;
        }
        if (str_starts_with($path, 'docs/implementation/')) {
            $relative = substr($path, strlen('docs/implementation/'));
            return $relative !== '' && !str_contains($relative, '/');
        }
        return false;
    }

    public function category(string $path): string
    {
        $path = PathPolicy::normalize($path);
        if (
            in_array($path, self::ROOT_FILES, true)
            || str_starts_with($path, '.github/')
            || str_starts_with($path, 'routes/')
            || str_starts_with($path, 'database/')
            || str_starts_with($path, 'resources/translations/')
            || str_starts_with($path, 'resources/views/')
            || str_starts_with($path, 'src/Bootstrap/Module/')
        ) {
            return self::EXTENSION;
        }
        return self::FROZEN;
    }
}
