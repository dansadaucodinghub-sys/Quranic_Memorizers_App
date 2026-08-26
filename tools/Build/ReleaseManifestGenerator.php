<?php

declare(strict_types=1);

namespace Qmdb\Tools\Build;

use Qmdb\Tools\Support\FileSystem;
use Qmdb\Tools\Support\GitMetadata;
use Qmdb\Tools\Support\JsonFile;

final class ReleaseManifestGenerator
{
    public function __construct(private readonly ReleaseFilePolicy $policy = new ReleaseFilePolicy())
    {
    }

    public function generate(
        string $root,
        GitMetadata $git,
        string $composerLockHash,
        string $packageLockHash,
        string $sbomHash,
        string $licenceHash,
    ): ReleaseManifest {
        $entries = [];
        foreach (FileSystem::files($root) as $file) {
            $relative = FileSystem::relative($root, $file);
            if ($relative === 'release-manifest.json') {
                continue;
            }
            if (is_link($file) || !$this->policy->isAllowed($relative)) {
                throw new \RuntimeException('Release manifest rejected path: ' . $relative);
            }
            $hash = hash_file('sha256', $file);
            $size = filesize($file);
            if (!is_string($hash) || !is_int($size)) {
                throw new \RuntimeException('Unable to inspect release file: ' . $relative);
            }
            $entries[] = [
                'path' => $relative,
                'sha256' => $hash,
                'size' => $size,
                'mode' => sprintf('%04o', $this->policy->mode($relative)),
            ];
        }
        usort($entries, static fn (array $left, array $right): int => strcmp($left['path'], $right['path']));
        return new ReleaseManifest([
            'project' => 'Qur\'an Memorizer DB',
            'project_code' => 'QMDB',
            'application_version' => '0.1.0-dev',
            'source_revision' => $git->revision,
            'source_revision_short' => $git->shortRevision,
            'source_date_epoch' => $git->sourceDateEpoch,
            'source_state' => $git->sourceState,
            'release_eligible' => $git->sourceState === 'clean',
            'frozen_baseline' => 'QMDB-P0-FRZ-001',
            'approved_changes' => ['QMDB-CR-001'],
            'php_requirement' => '^8.5',
            'mysql_baseline' => '8.4.11',
            'build_tool_version' => '1.0.0',
            'composer_lock_sha256' => $composerLockHash,
            'package_lock_sha256' => $packageLockHash,
            'sbom_sha256' => $sbomHash,
            'runtime_licence_inventory_sha256' => $licenceHash,
            'file_count' => count($entries),
            'files' => $entries,
        ]);
    }

    public function write(string $path, ReleaseManifest $manifest): string
    {
        JsonFile::writeObject($path, $manifest->data());
        $hash = hash_file('sha256', $path);
        if (!is_string($hash)) {
            throw new \RuntimeException('Unable to hash release manifest.');
        }
        return $hash;
    }
}
