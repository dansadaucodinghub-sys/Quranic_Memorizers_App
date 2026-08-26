<?php

declare(strict_types=1);

namespace Qmdb\Tools\Sbom;

use Qmdb\Tools\Ci\VerificationReport;
use Qmdb\Tools\Support\JsonFile;

final class SbomValidator
{
    public function validate(string $root, string $path): VerificationReport
    {
        $report = new VerificationReport();
        try {
            $bom = JsonFile::readObject($path);
            $inventory = (new ComposerInventory())->packages($root . '/composer.lock');
        } catch (\Throwable $exception) {
            $report->check(false, 'SBOM input is invalid: ' . $exception->getMessage());
            return $report;
        }
        $report->check(
            ($bom['bomFormat'] ?? null) === ProductionSbomGenerator::FORMAT,
            'SBOM format must be CycloneDX.',
        );
        $report->check(
            ($bom['specVersion'] ?? null) === ProductionSbomGenerator::SPEC_VERSION,
            'SBOM schema must be 1.6.',
        );
        $metadata = is_array($bom['metadata'] ?? null) ? $bom['metadata'] : [];
        $application = $metadata['component'] ?? null;
        $report->check(
            is_array($application)
                && ($application['bom-ref'] ?? null) === ProductionSbomGenerator::APPLICATION_REF,
            'Application component is missing.',
        );

        $properties = [];
        $applicationProperties = is_array($application) && is_array($application['properties'] ?? null)
            ? $application['properties']
            : [];
        foreach ($applicationProperties as $property) {
            if (is_array($property) && is_string($property['name'] ?? null) && is_string($property['value'] ?? null)) {
                $properties[$property['name']] = $property['value'];
            }
        }
        $report->check(
            ($properties['qmdb:frozen-baseline'] ?? null) === 'QMDB-P0-FRZ-001',
            'Frozen baseline is missing from SBOM.',
        );
        $report->check(
            preg_match('/^[a-f0-9]{40}$/', $properties['qmdb:source-revision'] ?? '') === 1,
            'Source revision is missing from SBOM.',
        );

        $components = is_array($bom['components'] ?? null) ? $bom['components'] : [];
        $refs = [];
        $names = [];
        foreach ($components as $component) {
            if (!is_array($component) || !is_string($component['bom-ref'] ?? null)) {
                $report->check(false, 'SBOM component is invalid.');
                continue;
            }
            $refs[] = $component['bom-ref'];
            $purl = $component['purl'] ?? '';
            if (is_string($purl) && preg_match('#^pkg:composer/([^@]+)@#', $purl, $match) === 1) {
                $names[] = $match[1];
            }
        }
        $sortedRefs = $refs;
        sort($sortedRefs, SORT_STRING);
        $report->check($refs === $sortedRefs, 'SBOM components must be sorted deterministically.');
        $report->check(count($refs) === count(array_unique($refs)), 'SBOM component references must be unique.');
        foreach ($inventory['runtime'] as $package) {
            $report->check(
                in_array($package['name'], $names, true),
                'Runtime package missing from SBOM: ' . $package['name'],
            );
        }
        foreach ($inventory['development'] as $package) {
            $report->check(
                !in_array($package['name'], $names, true),
                'Development package present in SBOM: ' . $package['name'],
            );
        }

        $validRefs = array_fill_keys(array_merge([ProductionSbomGenerator::APPLICATION_REF], $refs), true);
        foreach (is_array($bom['dependencies'] ?? null) ? $bom['dependencies'] : [] as $dependency) {
            if (!is_array($dependency) || !is_string($dependency['ref'] ?? null)) {
                $report->check(false, 'SBOM dependency entry is invalid.');
                continue;
            }
            $report->check(
                isset($validRefs[$dependency['ref']]),
                'SBOM dependency reference is unresolved: ' . $dependency['ref'],
            );
            foreach (is_array($dependency['dependsOn'] ?? null) ? $dependency['dependsOn'] : [] as $reference) {
                $report->check(
                    is_string($reference) && isset($validRefs[$reference]),
                    'SBOM dependency target is unresolved.',
                );
            }
        }
        $json = json_encode($bom, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $report->check(
            preg_match('#(?:^|["\s])[A-Za-z]:[\\\\/]|/home/|/Users/#', $json) !== 1,
            'SBOM contains a local absolute path.',
        );
        $report->check(
            preg_match('/-----BEGIN .*PRIVATE KEY-----|\bgh[pousr]_[A-Za-z0-9]{30,}\b/', $json) !== 1,
            'SBOM contains a secret-like value.',
        );
        return $report;
    }
}
