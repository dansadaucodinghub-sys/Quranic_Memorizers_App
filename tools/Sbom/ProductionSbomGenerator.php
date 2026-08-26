<?php

declare(strict_types=1);

namespace Qmdb\Tools\Sbom;

use Qmdb\Tools\Support\GitMetadata;
use Qmdb\Tools\Support\JsonFile;

final class ProductionSbomGenerator
{
    public const FORMAT = 'CycloneDX';
    public const SPEC_VERSION = '1.6';
    public const APPLICATION_REF = 'pkg:generic/qmdb@0.1.0-dev';

    /** @return array<string, mixed> */
    public function generate(string $root, ?GitMetadata $git = null): array
    {
        $git ??= GitMetadata::inspect($root);
        $inventory = (new ComposerInventory())->packages($root . '/composer.lock');
        $components = [];
        $references = [];
        foreach ($inventory['runtime'] as $package) {
            $name = $package['name'];
            $version = $package['version'];
            $reference = 'pkg:composer/' . $name . '@' . rawurlencode($version);
            $references[$name] = $reference;
            $component = [
                'type' => 'library',
                'bom-ref' => $reference,
                'group' => str_contains($name, '/') ? strstr($name, '/', true) : '',
                'name' => str_contains($name, '/') ? substr($name, strpos($name, '/') + 1) : $name,
                'version' => $version,
                'purl' => $reference,
                'properties' => [
                    ['name' => 'qmdb:dependency-scope', 'value' => 'runtime'],
                    [
                        'name' => 'qmdb:package-type',
                        'value' => is_string($package['type'] ?? null) ? $package['type'] : 'library',
                    ],
                ],
            ];
            $licenses = $this->licenses($package['license'] ?? null);
            if ($licenses !== []) {
                $component['licenses'] = $licenses;
            }
            $dist = is_array($package['dist'] ?? null) ? $package['dist'] : [];
            $sha = $dist['shasum'] ?? null;
            if (is_string($sha) && preg_match('/^[a-f0-9]{40}$/i', $sha) === 1) {
                $component['hashes'] = [['alg' => 'SHA-1', 'content' => strtolower($sha)]];
            }
            $source = is_array($package['source'] ?? null) ? $package['source'] : [];
            $sourceUrl = $source['url'] ?? null;
            if (is_string($sourceUrl) && preg_match('#^https://#', $sourceUrl) === 1) {
                $component['externalReferences'] = [['type' => 'vcs', 'url' => $sourceUrl]];
            }
            $components[] = $component;
        }
        usort($components, static fn (array $left, array $right): int => strcmp($left['bom-ref'], $right['bom-ref']));

        $dependencies = [[
            'ref' => self::APPLICATION_REF,
            'dependsOn' => array_values($references),
        ]];
        foreach ($inventory['runtime'] as $package) {
            $required = [];
            foreach (array_keys(is_array($package['require'] ?? null) ? $package['require'] : []) as $name) {
                if (isset($references[$name])) {
                    $required[] = $references[$name];
                }
            }
            sort($required, SORT_STRING);
            $dependencies[] = ['ref' => $references[$package['name']], 'dependsOn' => $required];
        }
        usort($dependencies, static fn (array $left, array $right): int => strcmp($left['ref'], $right['ref']));

        return [
            'bomFormat' => self::FORMAT,
            'specVersion' => self::SPEC_VERSION,
            'version' => 1,
            'metadata' => [
                'tools' => ['components' => [[
                    'type' => 'application',
                    'name' => 'qmdb-repository-sbom-generator',
                    'version' => '1.0.0',
                ]]],
                'component' => [
                    'type' => 'application',
                    'bom-ref' => self::APPLICATION_REF,
                    'name' => 'Qur\'an Memorizer DB',
                    'version' => '0.1.0-dev',
                    'properties' => [
                        ['name' => 'qmdb:approved-change', 'value' => 'QMDB-CR-001'],
                        ['name' => 'qmdb:frozen-baseline', 'value' => 'QMDB-P0-FRZ-001'],
                        ['name' => 'qmdb:source-date-epoch', 'value' => (string) $git->sourceDateEpoch],
                        ['name' => 'qmdb:source-revision', 'value' => $git->revision],
                    ],
                ],
            ],
            'components' => $components,
            'dependencies' => $dependencies,
        ];
    }

    public function write(string $root, string $output, ?GitMetadata $git = null): string
    {
        JsonFile::writeObject($output, $this->generate($root, $git));
        $hash = hash_file('sha256', $output);
        if (!is_string($hash)) {
            throw new \RuntimeException('Unable to hash SBOM.');
        }
        $checksum = $hash . '  ' . basename($output) . "\n";
        if (file_put_contents($output . '.sha256', $checksum) === false) {
            throw new \RuntimeException('Unable to write SBOM checksum.');
        }
        return $hash;
    }

    /** @return list<array{license: array{id: string}}> */
    private function licenses(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $licenses = [];
        foreach ($raw as $license) {
            if (is_string($license) && trim($license) !== '') {
                $licenses[] = ['license' => ['id' => trim($license)]];
            }
        }
        usort(
            $licenses,
            static fn (array $left, array $right): int => strcmp($left['license']['id'], $right['license']['id']),
        );
        return $licenses;
    }
}
