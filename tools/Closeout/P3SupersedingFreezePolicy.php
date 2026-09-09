<?php

declare(strict_types=1);

namespace Qmdb\Tools\Closeout;

use Qmdb\Tools\Policy\PathPolicy;
use Qmdb\Tools\Support\FileSystem;

final class P3SupersedingFreezePolicy
{
    public const MANIFEST = 'docs/closeout/p3/qmdb-p3-governance-extension-freeze.yaml';

    /** @return list<array{path:string,category:string,sha256:string}> */
    public function entries(string $root): array
    {
        $entries = [];
        foreach (FileSystem::files($root) as $absolute) {
            $path = str_replace('\\', '/', FileSystem::relative($root, $absolute));
            if (!$this->isIncluded($path)) {
                continue;
            }
            if (is_link($absolute) || (new PathPolicy())->repositoryViolation($path) !== null) {
                throw new \RuntimeException('P3 superseding freeze rejects governed path: ' . $path);
            }
            $hash = hash_file('sha256', $absolute);
            if (!is_string($hash)) {
                throw new \RuntimeException('Unable to hash P3 superseding governed file: ' . $path);
            }
            $entries[] = ['path' => $path, 'category' => 'FROZEN_P3_EFFECTIVE_BASELINE', 'sha256' => $hash];
        }
        usort($entries, static fn (array $left, array $right): int => strcmp($left['path'], $right['path']));
        return $entries;
    }

    public function isIncluded(string $path): bool
    {
        $path = PathPolicy::normalize($path);
        if ($path === self::MANIFEST || $path === PostP3ExtensionLedger::PATH) {
            return false;
        }
        if ((new P3FreezePolicy())->isIncluded($path)) {
            return true;
        }
        return $path === 'docs/project/change-requests/QMDB-CR-002-post-p3-additive-extension-governance.md'
            || $path === 'docs/implementation/reports/QMDB-PHASE-MAPPING-CORRECTION-001.md'
            || $path === 'docs/implementation/reports/QMDB-P4-B01-scope-resolution.md'
            || $path === 'docs/closeout/p3/p3-freeze-supersession-equivalence.md';
    }
}
