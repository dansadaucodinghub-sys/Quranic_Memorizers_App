<?php

declare(strict_types=1);

namespace Qmdb\Tools\Sbom;

use Qmdb\Tools\Support\JsonFile;

/** @phpstan-import-type ComposerPackage from ComposerInventory */
final class RuntimeLicenceGenerator
{
    /**
     * @return array{runtime_count: int, development_count: int, unknown_runtime: int,
     *     review_runtime: int, runtime_json: string, runtime_markdown: string}
     */
    public function generate(string $root, string $outputDirectory): array
    {
        $inventory = (new ComposerInventory())->packages($root . '/composer.lock');
        $runtime = $this->entries($inventory['runtime'], 'runtime');
        $development = $this->entries($inventory['development'], 'development');
        $unknown = count(array_filter(
            $runtime,
            static fn (array $entry): bool => $entry['review'] === 'UNKNOWN_LICENCE',
        ));
        $review = count(array_filter($runtime, static fn (array $entry): bool => $entry['review'] !== 'NONE'));
        $runtimeJson = $outputDirectory . '/runtime-licences.json';
        $developmentJson = $outputDirectory . '/development-licences.json';
        JsonFile::writeObject($runtimeJson, [
            'schema_version' => 1,
            'legal_notice' => 'Inventory only; not a legal conclusion.',
            'classification' => 'runtime',
            'packages' => $runtime,
            'unknown_licences' => $unknown,
            'review_required' => $review,
        ]);
        JsonFile::writeObject($developmentJson, [
            'schema_version' => 1,
            'legal_notice' => 'Inventory only; not a legal conclusion.',
            'classification' => 'development',
            'packages' => $development,
        ]);
        $runtimeMarkdown = $outputDirectory . '/runtime-licences.md';
        $developmentMarkdown = $outputDirectory . '/development-licences.md';
        $this->writeMarkdown($runtimeMarkdown, 'Runtime', $runtime);
        $this->writeMarkdown($developmentMarkdown, 'Development', $development);
        foreach ([$runtimeJson, $runtimeMarkdown] as $path) {
            $hash = hash_file('sha256', $path);
            $checksum = is_string($hash) ? $hash . '  ' . basename($path) . "\n" : '';
            if (!is_string($hash) || file_put_contents($path . '.sha256', $checksum) === false) {
                throw new \RuntimeException('Unable to write licence inventory checksum.');
            }
        }
        return [
            'runtime_count' => count($runtime),
            'development_count' => count($development),
            'unknown_runtime' => $unknown,
            'review_runtime' => $review,
            'runtime_json' => $runtimeJson,
            'runtime_markdown' => $runtimeMarkdown,
        ];
    }

    /** @param list<ComposerPackage> $packages
     * @return list<array{name: string, version: string, classification: string,
     *     licences: list<string>, source: string, review: string}>
     */
    private function entries(array $packages, string $classification): array
    {
        $entries = [];
        foreach ($packages as $package) {
            $licenses = array_values(array_filter(
                is_array($package['license'] ?? null) ? $package['license'] : [],
                'is_string',
            ));
            sort($licenses, SORT_STRING);
            $review = $licenses === [] ? 'UNKNOWN_LICENCE' : 'NONE';
            if (preg_match('/(?:^|-)A?GPL-/i', implode(' ', $licenses)) === 1) {
                $review = 'RECIPROCAL_LICENCE_REVIEW';
            }
            $sourceMetadata = is_array($package['source'] ?? null) ? $package['source'] : [];
            $distMetadata = is_array($package['dist'] ?? null) ? $package['dist'] : [];
            $source = $sourceMetadata['url'] ?? $distMetadata['url'] ?? '';
            $entries[] = [
                'name' => $package['name'],
                'version' => $package['version'],
                'classification' => $classification,
                'licences' => $licenses,
                'source' => is_string($source) ? $source : '',
                'review' => $review,
            ];
        }
        return $entries;
    }

    /**
     * @param list<array{name: string, version: string, classification: string,
     *     licences: list<string>, source: string, review: string}> $entries
     */
    private function writeMarkdown(string $path, string $classification, array $entries): void
    {
        $lines = [
            '# QMDB ' . $classification . ' Dependency Licence Inventory',
            '',
            '> Inventory only; this document is not a legal conclusion.',
            '',
            '| Package | Version | Declared licence | Review | Source |',
            '| --- | --- | --- | --- | --- |',
        ];
        foreach ($entries as $entry) {
            $lines[] = sprintf(
                '| `%s` | `%s` | %s | %s | %s |',
                $entry['name'],
                $entry['version'],
                $entry['licences'] === [] ? 'Unknown' : implode(', ', $entry['licences']),
                $entry['review'],
                $entry['source'] === '' ? 'Not declared' : $entry['source'],
            );
        }
        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0755, true) && !is_dir(dirname($path))) {
            throw new \RuntimeException('Unable to create licence report directory.');
        }
        if (file_put_contents($path, implode("\n", $lines) . "\n") === false) {
            throw new \RuntimeException('Unable to write licence inventory.');
        }
    }
}
