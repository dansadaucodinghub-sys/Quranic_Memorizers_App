<?php

declare(strict_types=1);

namespace Qmdb\Tools\Build;

use Qmdb\Tools\Sbom\ComposerInventory;
use Qmdb\Tools\Sbom\ProductionSbomGenerator;
use Qmdb\Tools\Sbom\RuntimeLicenceGenerator;
use Qmdb\Tools\Sbom\SbomValidator;
use Qmdb\Tools\Support\FileSystem;
use Qmdb\Tools\Support\GitMetadata;
use Qmdb\Tools\Support\JsonFile;
use Qmdb\Tools\Support\ProcessRunner;

final class ReleaseBuilder
{
    public function __construct(
        private readonly ReleaseFilePolicy $policy = new ReleaseFilePolicy(),
        private readonly ProcessRunner $runner = new ProcessRunner(),
    ) {
    }

    /** @return array<string, mixed> */
    public function build(string $root): array
    {
        $git = GitMetadata::inspect($root, $this->runner);
        $buildRoot = $root . '/build';
        $reports = $buildRoot . '/reports';
        $releaseDirectory = $buildRoot . '/release';
        $stage = $buildRoot . '/staging/qmdb';
        foreach ([$reports, $releaseDirectory, dirname($stage)] as $directory) {
            if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new \RuntimeException('Unable to create build directory: ' . $directory);
            }
        }
        FileSystem::removeTree($root, $releaseDirectory);
        if (!mkdir($releaseDirectory, 0755, true) && !is_dir($releaseDirectory)) {
            throw new \RuntimeException('Unable to create clean release directory.');
        }
        FileSystem::removeTree($root, $stage);
        if (!mkdir($stage, 0755, true) && !is_dir($stage)) {
            throw new \RuntimeException('Unable to create isolated release stage.');
        }

        try {
            $sbomPath = $reports . '/production-sbom.cdx.json';
            $sbomHash = (new ProductionSbomGenerator())->write($root, $sbomPath, $git);
            $sbomReport = (new SbomValidator())->validate($root, $sbomPath);
            if (!$sbomReport->passed()) {
                throw new \RuntimeException(
                    'Generated production SBOM failed validation: ' . implode('; ', $sbomReport->errors()),
                );
            }
            $licence = (new RuntimeLicenceGenerator())->generate($root, $reports);
            if ($licence['unknown_runtime'] !== 0) {
                throw new \RuntimeException('Unknown runtime licences require manual review before artifact creation.');
            }

            foreach ($this->policy->sourceFiles($root) as $relative) {
                FileSystem::copyFile($root . '/' . $relative, $stage . '/' . $relative, $this->policy->mode($relative));
            }
            $install = $this->runner->run([
                'composer', 'install', '--no-dev', '--no-interaction', '--prefer-dist', '--no-progress',
                '--optimize-autoloader', '--classmap-authoritative',
            ], $stage);
            if ($install->exitCode !== 0) {
                throw new \RuntimeException('Production Composer installation failed: ' . $install->output());
            }
            $this->pruneVendorDevelopmentFiles($stage);
            $platform = $this->runner->run(['composer', 'check-platform-reqs', '--no-dev'], $stage);
            if ($platform->exitCode !== 0) {
                throw new \RuntimeException('Production platform requirements failed: ' . $platform->output());
            }
            $this->verifyProductionDependencies($root, $stage);

            FileSystem::copyFile($sbomPath, $stage . '/metadata/production-sbom.cdx.json');
            FileSystem::copyFile($licence['runtime_json'], $stage . '/metadata/runtime-licences.json');
            FileSystem::copyFile($licence['runtime_markdown'], $stage . '/metadata/runtime-licences.md');

            $composerLockHash = $this->hash($root . '/composer.lock');
            $packageLockHash = $this->hash($root . '/package-lock.json');
            $licenceHash = $this->hash($licence['runtime_json']);
            $manifestGenerator = new ReleaseManifestGenerator($this->policy);
            $manifest = $manifestGenerator->generate(
                $stage,
                $git,
                $composerLockHash,
                $packageLockHash,
                $sbomHash,
                $licenceHash,
            );
            $manifestPath = $stage . '/release-manifest.json';
            $manifestHash = $manifestGenerator->write($manifestPath, $manifest);

            $filename = sprintf('qmdb-0.1.0-dev-%s.tar.gz', $git->shortRevision);
            if (!$this->policy->validateArchiveFilename($filename)) {
                throw new \RuntimeException('Generated release filename is invalid.');
            }
            $archive = $releaseDirectory . '/' . $filename;
            $archiveHash = (new ReleaseArchiveBuilder($this->policy))->build($stage, $archive, $git->sourceDateEpoch);
            $externalManifest = $releaseDirectory . '/release-manifest.json';
            FileSystem::copyFile($manifestPath, $externalManifest);
            FileSystem::copyFile($sbomPath, $releaseDirectory . '/production-sbom.cdx.json');
            FileSystem::copyFile($licence['runtime_json'], $releaseDirectory . '/runtime-licences.json');
            FileSystem::copyFile($licence['runtime_markdown'], $releaseDirectory . '/runtime-licences.md');

            $checksumEntries = [
                $filename => $archiveHash,
                'production-sbom.cdx.json' => $sbomHash,
                'release-manifest.json' => $manifestHash,
                'runtime-licences.json' => $licenceHash,
                'runtime-licences.md' => $this->hash($licence['runtime_markdown']),
            ];
            ksort($checksumEntries, SORT_STRING);
            $lines = [];
            foreach ($checksumEntries as $name => $hash) {
                $lines[] = $hash . '  ' . $name;
            }
            $checksums = $releaseDirectory . '/SHA256SUMS';
            if (file_put_contents($checksums, implode("\n", $lines) . "\n", LOCK_EX) === false) {
                throw new \RuntimeException('Unable to write SHA256SUMS.');
            }
            $archiveSize = filesize($archive);
            if (!is_int($archiveSize)) {
                throw new \RuntimeException('Unable to determine archive size.');
            }
            $result = [
                'status' => 'built',
                'artifact' => $archive,
                'artifact_filename' => $filename,
                'archive_sha256' => $archiveHash,
                'archive_size' => $archiveSize,
                'manifest_sha256' => $manifestHash,
                'sbom_sha256' => $sbomHash,
                'runtime_licence_inventory_sha256' => $licenceHash,
                'file_count' => $manifest->data()['file_count'],
                'runtime_dependencies' => count(
                    (new ComposerInventory())->packages($root . '/composer.lock')['runtime'],
                ),
                'source_revision' => $git->revision,
                'source_state' => $git->sourceState,
                'release_eligible' => $git->sourceState === 'clean',
                'source_date_epoch' => $git->sourceDateEpoch,
            ];
            JsonFile::writeObject($reports . '/release-build.json', $result);
            return $result;
        } finally {
            FileSystem::removeTree($root, $stage);
        }
    }

    private function verifyProductionDependencies(string $root, string $stage): void
    {
        $expected = (new ComposerInventory())->packages($root . '/composer.lock');
        $allowed = [];
        foreach ($expected['runtime'] as $package) {
            $allowed[$package['name']] = true;
        }
        $installed = JsonFile::readObject($stage . '/vendor/composer/installed.json');
        $packages = $installed['packages'] ?? null;
        if (!is_array($packages) || !array_is_list($packages)) {
            throw new \RuntimeException('Production Composer installed metadata is invalid.');
        }
        foreach ($packages as $package) {
            if (!is_array($package) || !is_string($package['name'] ?? null)) {
                throw new \RuntimeException('Production Composer package metadata is invalid.');
            }
            if (!isset($allowed[$package['name']])) {
                throw new \RuntimeException(
                    'Development or unexpected dependency entered production stage: ' . $package['name'],
                );
            }
        }
    }

    private function pruneVendorDevelopmentFiles(string $stage): void
    {
        foreach (FileSystem::files($stage . '/vendor') as $file) {
            $relative = FileSystem::relative($stage, $file);
            if ($this->policy->isAllowed($relative)) {
                continue;
            }
            if (!unlink($file)) {
                throw new \RuntimeException('Unable to remove development-only vendor file: ' . $relative);
            }
        }
    }

    private function hash(string $path): string
    {
        $hash = hash_file('sha256', $path);
        if (!is_string($hash)) {
            throw new \RuntimeException('Unable to hash file: ' . $path);
        }
        return $hash;
    }
}
