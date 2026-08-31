<?php

declare(strict_types=1);

namespace Qmdb\Tools\Closeout;

use Qmdb\Tools\Policy\PathPolicy;
use Qmdb\Tools\Support\FileSystem;

/**
 * Defines the P2 identity, security and tenant-isolation freeze boundary.
 * The policy intentionally permits only explicit controlled extension points.
 */
final class P2FreezePolicy
{
    public const FROZEN = 'FROZEN_P2_IDENTITY_SECURITY_TENANCY';
    public const EXTENSION = 'CONTROLLED_EXTENSION_POINT';

    /** @var list<string> */
    private const ROOT_FILES = [
        '.editorconfig', '.env.example', '.gitattributes', '.gitignore', '.npmrc', '.nvmrc', 'README.md',
        'compose.mysql-test.yaml', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json',
        'phpcs.xml.dist', 'phpstan.neon.dist', 'phpunit.xml.dist',
    ];

    /** @var list<string> */
    private const SOURCE_PREFIXES = [
        '.github/', 'bin/', 'config/', 'database/', 'public/', 'resources/', 'routes/', 'scripts/', 'src/', 'tests/', 'tools/',
    ];

    /** @var list<string> */
    private const GENERATED_PREFIXES = [
        '.git/', '.runtime/', '.phpstan.cache/', 'build/', 'coverage/', 'node_modules/', 'vendor/',
    ];

    /** @var list<string> */
    private const P3_B01_EXTENSION_PREFIXES = [
        'src/Modules/Geography/',
        'src/Bootstrap/Module/GeographyReferenceModule.php',
        'database/reference/nigeria-administrative-areas-v1.json',
        'resources/views/components/geography-',
        'resources/views/fragments/geography-',
        'resources/views/pages/nigeria-',
        'public/assets/js/geography-',
        'tests/Geography/',
        'tests/Unit/Modules/Geography/',
        'tests/Integration/MySql/Geography',
        'tests/Architecture/Geography',
        'tests/Frontend/geography-',
    ];

    /** @var list<string> */
    private const P3_B01_MUTABLE_EXISTING_PATHS = [
        'src/Bootstrap/ApplicationFactory.php',
        'src/Bootstrap/ApplicationMetadata.php',
        'src/Bootstrap/Module/ApplicationHttpModule.php',
        'src/Bootstrap/Module/ConsoleFoundationModule.php',
        'src/Bootstrap/Module/PresentationFoundationModule.php',
        'src/Modules/IdentitySessions/Interface/Http/ApplicationReadinessController.php',
        'src/Shared/Http/Routing/Security/ProductionRouteSecurityPolicyCatalog.php',
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
                throw new \RuntimeException('P2 freeze rejects symlink: ' . $path);
            }
            $violation = $this->pathPolicy->repositoryViolation($path);
            if ($violation !== null) {
                throw new \RuntimeException($violation);
            }
            $hash = hash_file('sha256', $absolute);
            if (!is_string($hash)) {
                throw new \RuntimeException('Unable to hash P2 governed file: ' . $path);
            }
            $entries[] = ['path' => $path, 'category' => $this->category($path), 'sha256' => $hash];
        }
        usort($entries, static fn (array $left, array $right): int => strcmp($left['path'], $right['path']));

        return $entries;
    }

    public function isIncluded(string $path): bool
    {
        $path = PathPolicy::normalize($path);
        if ($this->isP3B01Extension($path)) {
            return false;
        }
        if ($path === 'docs/closeout/p2/qmdb-p2-identity-security-tenancy-freeze.yaml') {
            return false;
        }
        foreach (self::GENERATED_PREFIXES as $prefix) {
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
        if (str_starts_with($path, 'docs/closeout/p2/')) {
            return true;
        }
        if (preg_match('#^docs/implementation/(?:P2-|identity-|account-|mfa-|roles-|tenant-|temporary-|security-events-|frontend-|presentation-|background-|ci-build-)#', $path) === 1) {
            return true;
        }
        if (preg_match('#^docs/implementation/reports/QMDB-P2-#', $path) === 1) {
            return true;
        }
        if (preg_match('#^docs/security/P2-#', $path) === 1 || $path === 'docs/operations/P2-security-performance-baseline.md') {
            return true;
        }

        return in_array($path, [
            'docs/project/project-state.md',
            'docs/project/decision-register.md',
            'docs/project/open-decisions.md',
            'docs/project/risk-register.md',
            'docs/implementation/requirements-to-implementation-map.md',
            'docs/implementation/definition-of-ready-and-done.md',
        ], true);
    }

    public function isP3B01Extension(string $path): bool
    {
        foreach (self::P3_B01_EXTENSION_PREFIXES as $prefix) {
            if (str_starts_with(PathPolicy::normalize($path), $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function isP3B01MutableExistingPath(string $path): bool
    {
        return in_array(PathPolicy::normalize($path), self::P3_B01_MUTABLE_EXISTING_PATHS, true);
    }

    public function category(string $path): string
    {
        $path = PathPolicy::normalize($path);
        if (
            in_array($path, self::ROOT_FILES, true)
            || str_starts_with($path, '.github/')
            || str_starts_with($path, 'database/')
            || str_starts_with($path, 'docs/')
            || str_starts_with($path, 'resources/')
            || str_starts_with($path, 'routes/')
            || str_starts_with($path, 'tests/')
            || str_starts_with($path, 'tools/')
        ) {
            return self::EXTENSION;
        }

        return self::FROZEN;
    }
}
