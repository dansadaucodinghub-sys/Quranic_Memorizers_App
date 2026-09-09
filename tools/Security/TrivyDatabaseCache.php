<?php

declare(strict_types=1);

namespace Qmdb\Tools\Security;

use Qmdb\Tools\Support\ProcessResult;
use Qmdb\Tools\Support\ProcessRunner;

/**
 * Owns Trivy vulnerability-database acquisition. A database is only published
 * after its downloaded bytes and signed metadata have passed local validation.
 */
final class TrivyDatabaseCache
{
    /** @var list<string> */
    public const REPOSITORIES = [
        'mirror.gcr.io/aquasec/trivy-db:2',
        'ghcr.io/aquasecurity/trivy-db:2',
        'public.ecr.aws/aquasecurity/trivy-db:2',
        'docker.io/aquasec/trivy-db:2',
    ];

    private const SCHEMA_VERSION = 2;
    private const MAX_AGE_SECONDS = 172800;
    private const FUTURE_SKEW_SECONDS = 300;

    /** @var \Closure(list<string>, string): ProcessResult */
    private \Closure $run;

    /** @param callable(list<string>, string): ProcessResult|null $runner */
    public function __construct(?callable $runner = null)
    {
        $this->run = $runner === null
            ? static fn (array $command, string $directory): ProcessResult => self::runNative($command, $directory)
            : \Closure::fromCallable($runner);
    }

    /**
     * @return array{version:int,updated_at:string,next_update:string,downloaded_at:string}
     */
    public function ensureCurrent(string $binary, string $cacheRoot, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        try {
            return $this->validate($cacheRoot, $now);
        } catch (TrivyDatabaseException $exception) {
            if (
                !in_array($exception->getCode(), [
                TrivyDatabaseException::DATABASE_UNAVAILABLE,
                TrivyDatabaseException::DATABASE_STALE,
                TrivyDatabaseException::SCHEMA_MISMATCH,
                TrivyDatabaseException::METADATA_INVALID,
                TrivyDatabaseException::CACHE_UNREADABLE,
                ], true)
            ) {
                throw $exception;
            }
        }

        return $this->refresh($binary, $cacheRoot, $now);
    }

    /**
     * @return array{version:int,updated_at:string,next_update:string,downloaded_at:string}
     */
    public function refresh(string $binary, string $cacheRoot, ?\DateTimeImmutable $now = null): array
    {
        if (!is_file($binary) || !is_readable($binary)) {
            throw new TrivyDatabaseException('Pinned Trivy binary is unavailable.', TrivyDatabaseException::POLICY_INVALID);
        }
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $parent = dirname($cacheRoot);
        if (!is_dir($parent) && !mkdir($parent, 0755, true) && !is_dir($parent)) {
            throw new TrivyDatabaseException('Trivy cache parent is unavailable.', TrivyDatabaseException::CACHE_UNREADABLE);
        }

        $errors = [];
        foreach (self::REPOSITORIES as $repository) {
            $temporary = $parent . '/.trivy-db-' . bin2hex(random_bytes(12));
            if (!mkdir($temporary, 0700)) {
                throw new TrivyDatabaseException('Unable to create temporary Trivy cache.', TrivyDatabaseException::CACHE_UNREADABLE);
            }
            try {
                $result = ($this->run)([
                    $binary,
                    'image',
                    '--download-db-only',
                    '--cache-dir',
                    $temporary,
                    '--db-repository',
                    $repository,
                    '--no-progress',
                ], $parent);
                if ($result->exitCode !== 0) {
                    $errors[] = $repository . ': ' . $this->summary($result->output());
                    continue;
                }
                $metadata = $this->validate($temporary, $now);
                $this->publish($temporary, $cacheRoot);
                return $metadata;
            } catch (TrivyDatabaseException $exception) {
                $errors[] = $repository . ': ' . $exception->getMessage();
            } finally {
                if (is_dir($temporary)) {
                    $this->removeTree($temporary);
                }
            }
        }

        throw new TrivyDatabaseException(
            'Trivy vulnerability database is unavailable from approved repositories: ' . implode(' | ', $errors),
            TrivyDatabaseException::DATABASE_UNAVAILABLE,
        );
    }

    /**
     * @return array{version:int,updated_at:string,next_update:string,downloaded_at:string}
     */
    public function validate(string $cacheRoot, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $database = $cacheRoot . '/db/trivy.db';
        $metadataPath = $cacheRoot . '/db/metadata.json';
        if (!is_dir($cacheRoot) || !is_readable($cacheRoot) || !is_file($database) || !is_readable($database) || filesize($database) < 1) {
            throw new TrivyDatabaseException('Trivy database cache is missing, empty, or unreadable.', TrivyDatabaseException::CACHE_UNREADABLE);
        }
        if (!is_file($metadataPath) || !is_readable($metadataPath)) {
            throw new TrivyDatabaseException('Trivy database metadata is missing or unreadable.', TrivyDatabaseException::METADATA_INVALID);
        }
        $raw = file_get_contents($metadataPath);
        try {
            $metadata = is_string($raw) ? json_decode($raw, true, 32, JSON_THROW_ON_ERROR) : null;
        } catch (\JsonException) {
            $metadata = null;
        }
        if (!is_array($metadata)) {
            throw new TrivyDatabaseException('Trivy database metadata is invalid.', TrivyDatabaseException::METADATA_INVALID);
        }
        if (($metadata['Version'] ?? null) !== self::SCHEMA_VERSION) {
            throw new TrivyDatabaseException('Trivy database schema version is incompatible.', TrivyDatabaseException::SCHEMA_MISMATCH);
        }
        $timestamps = [];
        foreach (['UpdatedAt', 'NextUpdate', 'DownloadedAt'] as $field) {
            $value = $metadata[$field] ?? null;
            if (!is_string($value)) {
                throw new TrivyDatabaseException('Trivy database metadata lacks ' . $field . '.', TrivyDatabaseException::METADATA_INVALID);
            }
            try {
                $timestamps[$field] = new \DateTimeImmutable($value);
            } catch (\Exception) {
                throw new TrivyDatabaseException('Trivy database metadata has an invalid ' . $field . '.', TrivyDatabaseException::METADATA_INVALID);
            }
            if ($field !== 'NextUpdate' && $timestamps[$field] > $now->modify('+' . self::FUTURE_SKEW_SECONDS . ' seconds')) {
                throw new TrivyDatabaseException('Trivy database metadata has a future ' . $field . '.', TrivyDatabaseException::METADATA_INVALID);
            }
        }
        if ($timestamps['NextUpdate'] <= $now || $timestamps['UpdatedAt'] < $now->modify('-' . self::MAX_AGE_SECONDS . ' seconds')) {
            throw new TrivyDatabaseException('Trivy database cache is stale.', TrivyDatabaseException::DATABASE_STALE);
        }

        return [
            'version' => self::SCHEMA_VERSION,
            'updated_at' => $timestamps['UpdatedAt']->format(DATE_ATOM),
            'next_update' => $timestamps['NextUpdate']->format(DATE_ATOM),
            'downloaded_at' => $timestamps['DownloadedAt']->format(DATE_ATOM),
        ];
    }

    private function publish(string $temporary, string $cacheRoot): void
    {
        $candidate = $temporary . '/db';
        if (!is_dir($candidate)) {
            throw new TrivyDatabaseException('Validated Trivy database directory is unavailable.', TrivyDatabaseException::CACHE_UNREADABLE);
        }
        $parent = dirname($cacheRoot);
        if (!is_dir($cacheRoot) && !mkdir($cacheRoot, 0755, true) && !is_dir($cacheRoot)) {
            throw new TrivyDatabaseException('Unable to create Trivy cache root.', TrivyDatabaseException::CACHE_UNREADABLE);
        }
        $destination = $cacheRoot . '/db';
        $backup = $parent . '/.trivy-db-backup-' . bin2hex(random_bytes(12));
        $hasExisting = is_dir($destination);
        if ($hasExisting && !rename($destination, $backup)) {
            throw new TrivyDatabaseException('Unable to preserve the current Trivy database during publication.', TrivyDatabaseException::CACHE_UNREADABLE);
        }
        try {
            if (!rename($candidate, $destination)) {
                throw new \RuntimeException('Unable to atomically publish the Trivy database.');
            }
            if ($hasExisting && is_dir($backup)) {
                $this->removeTree($backup);
            }
        } catch (\Throwable $exception) {
            if ($hasExisting && !is_dir($destination) && is_dir($backup)) {
                rename($backup, $destination);
            }
            throw new TrivyDatabaseException($exception->getMessage(), TrivyDatabaseException::CACHE_UNREADABLE, $exception);
        }
    }

    private function removeTree(string $path): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            if (!$item instanceof \SplFileInfo) {
                continue;
            }
            $itemPath = $item->getPathname();
            $item->isDir() ? rmdir($itemPath) : unlink($itemPath);
        }
        rmdir($path);
    }

    private function summary(string $output): string
    {
        $output = preg_replace('/\s+/', ' ', trim($output)) ?? '';
        return substr($output, 0, 300);
    }

    /** @param array<mixed> $command */
    private static function runNative(array $command, string $directory): ProcessResult
    {
        $normalized = [];
        foreach ($command as $argument) {
            if (!is_string($argument)) {
                throw new \InvalidArgumentException('Trivy command contains an invalid argument.');
            }
            $normalized[] = $argument;
        }
        if ($normalized === []) {
            throw new \InvalidArgumentException('Trivy command is empty.');
        }
        return (new ProcessRunner())->run($normalized, $directory);
    }
}
