<?php

declare(strict_types=1);

namespace Qmdb\Tools\Sbom;

use Qmdb\Tools\Support\JsonFile;

/**
 * @phpstan-type ComposerPackage array{name: string, version: string, type?: mixed,
 *     license?: mixed, dist?: mixed, source?: mixed, require?: mixed}
 */
final class ComposerInventory
{
    /**
     * @return array{runtime: list<ComposerPackage>, development: list<ComposerPackage>}
     */
    public function packages(string $composerLockPath): array
    {
        $lock = JsonFile::readObject($composerLockPath);
        $runtime = $this->normalizePackages($lock['packages'] ?? null);
        $development = $this->normalizePackages($lock['packages-dev'] ?? null);
        return ['runtime' => $runtime, 'development' => $development];
    }

    /** @return list<ComposerPackage> */
    private function normalizePackages(mixed $raw): array
    {
        if (!is_array($raw) || !array_is_list($raw)) {
            throw new \RuntimeException('Composer lock package list is invalid.');
        }
        $packages = [];
        foreach ($raw as $package) {
            if (
                !is_array($package)
                || !is_string($package['name'] ?? null)
                || !is_string($package['version'] ?? null)
            ) {
                throw new \RuntimeException('Composer lock package entry is invalid.');
            }
            $normalized = [
                'name' => $package['name'],
                'version' => $package['version'],
            ];
            foreach (['type', 'license', 'dist', 'source', 'require'] as $optional) {
                if (array_key_exists($optional, $package)) {
                    $normalized[$optional] = $package[$optional];
                }
            }
            $packages[] = $normalized;
        }
        usort($packages, static fn (array $left, array $right): int => strcmp($left['name'], $right['name']));
        return $packages;
    }
}
