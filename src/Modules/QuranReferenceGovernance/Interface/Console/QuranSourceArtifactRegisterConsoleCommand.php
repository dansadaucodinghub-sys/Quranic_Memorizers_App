<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Interface\Console;

use PDO;
use Qmdb\Modules\QuranReferenceGovernance\Domain\QuranSourceArtifactPathGuard;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

/**
 * Registers a local, operator-reviewed source artifact. It never downloads,
 * parses, or displays Qur'an source content.
 */
final readonly class QuranSourceArtifactRegisterConsoleCommand implements ConsoleCommand
{
    public function __construct(
        private string $approvedArtifactDirectory,
        private DatabaseConnectionProvider $connections,
        private QuranSourceArtifactPathGuard $paths,
    ) {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('quran:source-artifact:register');
    }

    public function description(): string
    {
        return 'Register one checked local Qur’an source artifact from the approved directory.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions(['source-code', 'artifact-code', 'artifact-role', 'path', 'media-type', 'acquisition-profile']);
        try {
            $sourceCode = $this->code($input->scalar('source-code'), 64, 'Source code');
            $artifactCode = $this->code($input->scalar('artifact-code'), 96, 'Artifact code');
            $artifactRole = $this->artifactRole($input->scalar('artifact-role'));
            $path = $this->paths->resolve($this->approvedArtifactDirectory, $this->required($input->scalar('path'), 512, 'Path'));
            $mediaType = $this->required($input->scalar('media-type'), 128, 'Media type');
            $profile = $this->code($input->scalar('acquisition-profile'), 96, 'Acquisition profile');
            $checksum = hash_file('sha256', $path, true);
            if (!is_string($checksum) || strlen($checksum) !== 32) {
                throw new \RuntimeException('Artifact checksum could not be calculated.');
            }
            $connection = $this->connections->connection();
            $source = $connection->prepare('SELECT id FROM quran_reference_sources WHERE source_code = :source_code AND status = \'APPROVED\'');
            $source->execute([':source_code' => $sourceCode]);
            $sourceId = $source->fetchColumn();
            if (!is_int($sourceId) && !is_string($sourceId)) {
                throw new \DomainException('Approved source is unavailable.');
            }
            $existing = $connection->prepare('SELECT public_id, artifact_code FROM quran_source_artifacts WHERE source_id = :source_id AND sha256 = :sha256');
            $existing->execute([':source_id' => (int) $sourceId, ':sha256' => $checksum]);
            $row = $existing->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                if (!hash_equals($artifactCode, (string) $row['artifact_code'])) {
                    throw new \DomainException('Artifact bytes are already registered under another artifact code.');
                }
                $output->write("Qur’an source artifact registration: PASS (idempotent)\nArtifact: " . UuidV7::fromBinary((string) $row['public_id'])->toString() . "\n");

                return 0;
            }
            $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $this->required($input->scalar('path'), 512, 'Path'));
            $insert = $connection->prepare('INSERT INTO quran_source_artifacts (public_id, source_id, artifact_code, artifact_role, repository_relative_path, original_filename, media_type, byte_size, sha256, acquisition_profile, acquired_at, status, version, created_at, updated_at, rejected_at) VALUES (:public_id, :source_id, :artifact_code, :artifact_role, :repository_relative_path, :original_filename, :media_type, :byte_size, :sha256, :acquisition_profile, UTC_TIMESTAMP(6), \'REGISTERED\', 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), NULL)');
            $publicId = UuidV7::generate();
            $insert->execute([
                ':public_id' => $publicId->toBinary(), ':source_id' => (int) $sourceId, ':artifact_code' => $artifactCode,
                ':artifact_role' => $artifactRole, ':repository_relative_path' => $relativePath, ':original_filename' => basename($path),
                ':media_type' => $mediaType, ':byte_size' => filesize($path), ':sha256' => $checksum, ':acquisition_profile' => $profile,
            ]);
            $output->write("Qur’an source artifact registration: PASS\nArtifact: " . $publicId->toString() . "\nChecksum: " . bin2hex($checksum) . "\n");

            return 0;
        } catch (\Throwable $exception) {
            $output->write("Qur’an source artifact registration: FAIL\n" . $exception->getMessage() . "\n");

            return 1;
        }
    }

    private function required(?string $value, int $maximum, string $label): string
    {
        if (!is_string($value) || $value === '' || str_contains($value, "\0") || strlen($value) > $maximum) {
            throw new \InvalidArgumentException($label . ' is invalid.');
        }

        return $value;
    }

    private function code(?string $value, int $maximum, string $label): string
    {
        $value = $this->required($value, $maximum, $label);
        if (preg_match('/\A[A-Z][A-Z0-9_]{1,' . ($maximum - 1) . '}\z/', $value) !== 1) {
            throw new \InvalidArgumentException($label . ' must be an uppercase stable code.');
        }

        return $value;
    }

    private function artifactRole(?string $value): string
    {
        $value = $this->required($value, 24, 'Artifact role');
        if (!in_array($value, ['CANONICAL_TEXT', 'STRUCTURAL_METADATA', 'SEARCH_TEXT'], true)) {
            throw new \InvalidArgumentException('Artifact role is not authorized.');
        }

        return $value;
    }
}
