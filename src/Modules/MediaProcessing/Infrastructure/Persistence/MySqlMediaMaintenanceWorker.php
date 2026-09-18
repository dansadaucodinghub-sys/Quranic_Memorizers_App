<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaProcessing\Infrastructure\Persistence;

use PDO;
use Qmdb\Modules\MediaIngestion\Application\{MediaBlobStore, MediaStagingCleaner};
use Qmdb\Modules\MediaProcessing\Application\MediaMaintenanceWorker;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;

/** Bounded maintenance. Holds retain bytes; reconciliation never grants delivery authority. */
final readonly class MySqlMediaMaintenanceWorker implements MediaMaintenanceWorker
{
    public function __construct(private DatabaseConnectionProvider $connections, private MediaBlobStore $storage, private MediaStagingCleaner $cleaner)
    {
    }

    public function run(string $operation, bool $dryRun = false): array
    {
        if (!in_array($operation, ['uploads:expire', 'staging:cleanup', 'assets:reconcile', 'storage:reconcile'], true)) {
            throw new \InvalidArgumentException('Unknown media maintenance operation.');
        }
        $pdo = $this->connections->connection();
        $pdo->beginTransaction();
        try {
            $result = match ($operation) {
                'uploads:expire' => $this->expire($pdo, $dryRun),
                'staging:cleanup' => $this->cleanup($pdo, $dryRun),
                default => $this->reconcile($pdo, $dryRun),
            };
            $pdo->commit();
            return $result;
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }

    /** @return array{examined:int,changed:int,dry_run:bool} */
    private function expire(PDO $pdo, bool $dryRun): array
    {
        $rows = $this->rows($pdo->query("SELECT u.id,u.workspace_id,u.asset_id FROM media_assets a JOIN media_upload_sessions u ON u.asset_id=a.id AND u.workspace_id=a.workspace_id WHERE u.status='OPEN' AND u.expires_at<=UTC_TIMESTAMP(6) AND a.status='STAGING' AND NOT EXISTS (SELECT 1 FROM media_holds h WHERE h.asset_id=a.id AND h.released_at IS NULL) ORDER BY u.expires_at,u.id LIMIT 20 FOR UPDATE SKIP LOCKED"));
        if (!$dryRun) {
            foreach ($rows as $row) {
                $pdo->prepare("UPDATE media_upload_sessions SET status='EXPIRED',updated_at=UTC_TIMESTAMP(6),version=version+1 WHERE id=? AND status='OPEN'")->execute([$row['id']]);
                $pdo->prepare("UPDATE media_assets SET status='WITHDRAWN',withdrawn_at=UTC_TIMESTAMP(6),updated_at=UTC_TIMESTAMP(6),version=version+1 WHERE id=? AND status='STAGING'")->execute([$row['asset_id']]);
                $this->event($pdo, $row, 'media.upload.expired');
            }
        }
        return ['examined' => count($rows), 'changed' => $dryRun ? 0 : count($rows), 'dry_run' => $dryRun];
    }

    /** @return array{examined:int,changed:int,dry_run:bool} */
    private function cleanup(PDO $pdo, bool $dryRun): array
    {
        // Immutable part rows are retained as evidence. A receipt makes repeated cleanup a no-op.
        $rows = $this->rows($pdo->query("SELECT p.id,p.workspace_id,u.asset_id,p.part_number,p.staging_object_key,p.sha256 FROM media_assets a JOIN media_upload_sessions u ON u.asset_id=a.id AND u.workspace_id=a.workspace_id JOIN media_upload_parts p ON p.upload_session_id=u.id AND p.workspace_id=u.workspace_id WHERE u.status IN ('EXPIRED','CANCELLED') AND u.updated_at<DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 24 HOUR) AND a.status IN ('STAGING','WITHDRAWN','REJECTED') AND NOT EXISTS (SELECT 1 FROM media_holds h WHERE h.asset_id=a.id AND h.released_at IS NULL) AND NOT EXISTS (SELECT 1 FROM media_events e WHERE e.asset_id=a.id AND e.event_code='media.staging.cleaned' AND JSON_EXTRACT(e.safe_metadata_json,'$.part_number')=p.part_number) ORDER BY u.updated_at,p.id LIMIT 20 FOR UPDATE SKIP LOCKED"));
        if (!$dryRun) {
            foreach ($rows as $row) {
                if (!is_string($row['staging_object_key']) || !is_string($row['sha256']) || !is_numeric($row['part_number'])) {
                    throw new \UnexpectedValueException('Invalid staging record.');
                }
                $this->cleaner->removeStaging($row['staging_object_key'], $row['sha256']);
                $statement = $pdo->prepare("INSERT INTO media_events (workspace_id,asset_id,event_code,safe_metadata_json,created_at) VALUES (?,?,'media.staging.cleaned',?,UTC_TIMESTAMP(6))");
                $statement->execute([$row['workspace_id'], $row['asset_id'], json_encode(['part_number' => (int) $row['part_number']], JSON_THROW_ON_ERROR)]);
            }
        }
        return ['examined' => count($rows), 'changed' => $dryRun ? 0 : count($rows), 'dry_run' => $dryRun];
    }

    /** @return array{examined:int,changed:int,dry_run:bool} */
    private function reconcile(PDO $pdo, bool $dryRun): array
    {
        $rows = $this->rows($pdo->query("SELECT a.id AS asset_id,a.workspace_id,a.original_storage_object_key,a.original_sha256,a.byte_size FROM media_assets a WHERE a.status IN ('QUARANTINED','PROCESSING','PENDING_MODERATION','APPROVED','PUBLISHED') ORDER BY a.updated_at,a.id LIMIT 20 FOR UPDATE SKIP LOCKED"));
        $changed = 0;
        foreach ($rows as $row) {
            if (!is_string($row['original_storage_object_key']) || !is_string($row['original_sha256']) || !is_numeric($row['byte_size'])) {
                throw new \UnexpectedValueException('Invalid media integrity record.');
            }
            $valid = $this->intact($row['original_storage_object_key'], $row['original_sha256'], (int) $row['byte_size']);
            $variants = $pdo->prepare("SELECT storage_object_key,sha256,byte_size FROM media_variants WHERE workspace_id=? AND asset_id=? AND status='READY' ORDER BY id LIMIT 20");
            $variants->execute([$row['workspace_id'], $row['asset_id']]);
            foreach ($this->rows($variants) as $variant) {
                if (!is_string($variant['storage_object_key']) || !is_string($variant['sha256']) || !is_numeric($variant['byte_size'])) {
                    throw new \UnexpectedValueException('Invalid media variant record.');
                }
                $valid = $this->intact($variant['storage_object_key'], $variant['sha256'], (int) $variant['byte_size']) && $valid;
            }
            if (!$dryRun) {
                if (!$valid) {
                    // Preserve bytes, holds and history; deny delivery immediately on any uncertainty.
                    $pdo->prepare("UPDATE media_assets SET status='WITHDRAWN',withdrawn_at=UTC_TIMESTAMP(6),updated_at=UTC_TIMESTAMP(6),version=version+1 WHERE id=?")->execute([$row['asset_id']]);
                    $this->event($pdo, $row, 'media.integrity.unavailable');
                    ++$changed;
                } else {
                    $pdo->prepare('UPDATE media_assets SET updated_at=UTC_TIMESTAMP(6) WHERE id=?')->execute([$row['asset_id']]);
                }
            }
        }
        return ['examined' => count($rows), 'changed' => $changed, 'dry_run' => $dryRun];
    }

    /** @return list<array<string,int|string|null>> */
    private function rows(\PDOStatement|false $statement): array
    {
        if ($statement === false) {
            throw new \RuntimeException('Media maintenance query failed.');
        }
        $rows = [];
        while (is_array($row = $statement->fetch(PDO::FETCH_ASSOC))) {
            $decoded = [];
            foreach ($row as $key => $value) {
                if (!is_string($key) || (!is_int($value) && !is_string($value) && $value !== null)) {
                    throw new \UnexpectedValueException('Invalid media maintenance row.');
                }
                $decoded[$key] = $value;
            }
            $rows[] = $decoded;
        }
        return $rows;
    }

    private function intact(string $key, string $checksum, int $bytes): bool
    {
        try {
            $contents = $this->storage->get($key);
            return strlen($contents) === $bytes && hash_equals($checksum, hash('sha256', $contents, true));
        } catch (\RuntimeException | \DomainException) {
            return false;
        }
    }

    /** @param array<string,mixed> $row */
    private function event(PDO $pdo, array $row, string $code): void
    {
        $pdo->prepare('INSERT INTO media_events (workspace_id,asset_id,event_code,created_at) VALUES (?,?,?,UTC_TIMESTAMP(6))')->execute([$row['workspace_id'], $row['asset_id'], $code]);
    }
}
