<?php

declare(strict_types=1);

namespace Qmdb\Tools\Closeout;

use Qmdb\Tools\Policy\PathPolicy;
use Qmdb\Tools\Support\FileSystem;

final class P3FreezePolicy
{
    public const FROZEN = 'FROZEN_P3_PEOPLE_GEOGRAPHY_ORGANIZATIONS';
    public const EXTENSION = 'CONTROLLED_EXTENSION_POINT';

    public function __construct(private readonly EngineeringFreezePolicy $engineering = new EngineeringFreezePolicy())
    {
    }

    /** @return list<array{path:string,category:string,sha256:string}> */
    public function entries(string $root): array
    {
        $entries = [];
        foreach (FileSystem::files($root) as $absolute) {
            $path = str_replace('\\', '/', FileSystem::relative($root, $absolute));
            if (!$this->isIncluded($path)) {
                continue;
            }
            if (is_link($absolute)) {
                throw new \RuntimeException('P3 freeze rejects symlink: ' . $path);
            }
            $violation = (new PathPolicy())->repositoryViolation($path);
            if ($violation !== null) {
                throw new \RuntimeException($violation);
            }
            $hash = hash_file('sha256', $absolute);
            if (!is_string($hash)) {
                throw new \RuntimeException('Unable to hash P3 governed file: ' . $path);
            }
            $entries[] = ['path' => $path, 'category' => $this->category($path), 'sha256' => $hash];
        }
        usort($entries, static fn (array $left, array $right): int => strcmp($left['path'], $right['path']));

        return $entries;
    }

    public function isIncluded(string $path): bool
    {
        $path = PathPolicy::normalize($path);
        if ($path === 'docs/closeout/p3/qmdb-p3-people-geography-organizations-participation-freeze.yaml') {
            return false;
        }
        if ($this->engineering->isIncluded($path)) {
            return true;
        }

        return str_starts_with($path, 'docs/closeout/p3/')
            || str_starts_with($path, 'docs/security/P3-')
            || $path === 'docs/operations/P3-security-performance-baseline.md'
            || str_starts_with($path, 'docs/implementation/reports/QMDB-P3-');
    }

    public function category(string $path): string
    {
        return str_starts_with(PathPolicy::normalize($path), 'docs/') ? self::EXTENSION : self::FROZEN;
    }
}
