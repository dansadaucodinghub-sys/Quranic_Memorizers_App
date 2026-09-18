<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaCatalog\Infrastructure\Persistence;

use DateTimeImmutable;
use PDO;
use PDOStatement;
use Qmdb\Modules\MediaCatalog\Application\MediaEvidenceRepository;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

/** Native prepared-statement P9 repository; all mutable aggregate reads lock the row. */
final readonly class MySqlMediaEvidenceRepository implements MediaEvidenceRepository
{
    public function __construct(private DatabaseConnectionProvider $connections)
    {
    }

    public function lock(int $workspaceId, UuidV7 $assetId): ?array
    {
        $statement = $this->statement('SELECT id,public_id,workspace_id,status,version,original_storage_object_key,byte_size,original_sha256 FROM media_assets WHERE workspace_id=:workspace AND public_id=:public_id FOR UPDATE');
        $statement->execute([':workspace' => $workspaceId, ':public_id' => $assetId->toBinary()]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        foreach (['id','workspace_id','version','byte_size'] as $field) {
            if (!is_int($row[$field]) && !(is_string($row[$field]) && ctype_digit($row[$field]))) {
                throw new \UnexpectedValueException('Media asset row is malformed.');
            }
        }
        foreach (['public_id','status','original_storage_object_key'] as $field) {
            if (!is_string($row[$field])) {
                throw new \UnexpectedValueException('Media asset row is malformed.');
            }
        }
        if ($row['original_sha256'] !== null && !is_string($row['original_sha256'])) {
            throw new \UnexpectedValueException('Media asset row is malformed.');
        }
        return ['id' => (int)$row['id'],'public_id' => UuidV7::fromBinary($row['public_id'])->toString(),'workspace_id' => (int)$row['workspace_id'],'status' => $row['status'],'version' => (int)$row['version'],'storage_key' => $row['original_storage_object_key'],'byte_size' => (int)$row['byte_size'],'sha256' => is_string($row['original_sha256']) ? $row['original_sha256'] : ''];
    }

    public function createStaged(int $workspaceId, int $actorAccountId, string $purpose, string $kind, string $safeFilename, string $storageKey, DateTimeImmutable $now): array
    {
        $id = UuidV7::generate();
        $time = $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
        $statement = $this->statement("INSERT INTO media_assets (public_id,workspace_id,created_by_account_id,purpose_code,media_kind,status,original_storage_provider_code,original_storage_object_key,original_filename_safe,byte_size,version,created_at,updated_at) VALUES (:public_id,:workspace,:actor,:purpose,:kind,'STAGING','LOCAL_PRIVATE',:storage_key,:filename,0,1,:created,:updated)");
        $statement->execute([':public_id' => $id->toBinary(),':workspace' => $workspaceId,':actor' => $actorAccountId,':purpose' => $purpose,':kind' => $kind,':storage_key' => $storageKey,':filename' => $safeFilename,':created' => $time,':updated' => $time]);
        $locked = $this->lock($workspaceId, $id);
        if ($locked === null) {
            throw new \RuntimeException('Staged media asset could not be locked.');
        }
        return ['id' => $locked['id'],'public_id' => $locked['public_id'],'status' => $locked['status'],'version' => $locked['version']];
    }

    public function finalizeUpload(array $asset, string $mimeType, int $byteSize, string $sha256, DateTimeImmutable $now): bool
    {
        if (strlen($sha256) !== 32 || $byteSize < 1) {
            throw new \InvalidArgumentException('Media integrity values are invalid.');
        }
        $statement = $this->statement("UPDATE media_assets SET status='QUARANTINED',detected_mime_type=:mime,byte_size=:bytes,original_sha256=:sha256,updated_at=:now,version=version+1 WHERE id=:id AND workspace_id=:workspace AND status='STAGING' AND version=:version");
        $statement->execute([':mime' => $mimeType,':bytes' => $byteSize,':sha256' => $sha256,':now' => $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),':id' => $asset['id'],':workspace' => $asset['workspace_id'],':version' => $asset['version']]);
        return $statement->rowCount() === 1;
    }

    public function transition(array $asset, string $target, DateTimeImmutable $now): bool
    {
        $statement = $this->statement('UPDATE media_assets SET status=:target,updated_at=:now,version=version+1 WHERE id=:id AND workspace_id=:workspace AND status=:status AND version=:version');
        $statement->execute([':target' => $target,':now' => $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),':id' => $asset['id'],':workspace' => $asset['workspace_id'],':status' => $asset['status'],':version' => $asset['version']]);
        return $statement->rowCount() === 1;
    }

    public function appendEvent(int $workspaceId, int $assetId, string $eventCode, ?int $actorAccountId, DateTimeImmutable $now): void
    {
        $statement = $this->statement('INSERT INTO media_events (workspace_id,asset_id,event_code,actor_account_id,created_at) VALUES (:workspace,:asset,:event,:actor,:now)');
        $statement->execute([':workspace' => $workspaceId,':asset' => $assetId,':event' => $eventCode,':actor' => $actorAccountId,':now' => $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u')]);
    }

    public function claimUploadSubmission(int $workspaceId, int $actorAccountId, UuidV7 $submission, string $requestFingerprint, DateTimeImmutable $now): array
    {
        if (strlen($requestFingerprint) !== 32) {
            throw new \InvalidArgumentException('Media submission fingerprint is invalid.');
        }
        $time = $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
        $insert = $this->statement("INSERT IGNORE INTO media_operation_receipts (submission_public_id,workspace_id,actor_account_id,operation_code,request_fingerprint,created_at) VALUES (:submission,:workspace,:actor,'UPLOAD',:fingerprint,:now)");
        $insert->execute([':submission' => $submission->toBinary(),':workspace' => $workspaceId,':actor' => $actorAccountId,':fingerprint' => $requestFingerprint,':now' => $time]);
        if ($insert->rowCount() === 1) {
            return ['state' => 'CLAIMED','asset_id' => null,'status' => null,'version' => null];
        }
        $select = $this->statement('SELECT workspace_id,actor_account_id,operation_code,request_fingerprint,asset_public_id,result_status,result_version,completed_at FROM media_operation_receipts WHERE submission_public_id=:submission FOR UPDATE');
        $select->execute([':submission' => $submission->toBinary()]);
        $row = $select->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || !is_string($row['request_fingerprint'] ?? null) || !is_string($row['operation_code'] ?? null)) {
            throw new \UnexpectedValueException('Media submission receipt is malformed.');
        }
        if (!is_numeric($row['workspace_id'] ?? null) || !is_numeric($row['actor_account_id'] ?? null)) {
            throw new \UnexpectedValueException('Media receipt ownership is malformed.');
        }
        if ((int)$row['workspace_id'] !== $workspaceId || (int)$row['actor_account_id'] !== $actorAccountId || $row['operation_code'] !== 'UPLOAD' || !hash_equals($row['request_fingerprint'], $requestFingerprint)) {
            return ['state' => 'CONFLICT','asset_id' => null,'status' => null,'version' => null];
        }
        if ($row['completed_at'] === null) {
            throw new \DomainException('A matching media upload is already in progress.');
        }
        if (!is_string($row['asset_public_id'] ?? null) || !is_string($row['result_status'] ?? null) || !is_numeric($row['result_version'] ?? null)) {
            throw new \UnexpectedValueException('Completed media submission receipt is malformed.');
        }
        return ['state' => 'REPLAY','asset_id' => UuidV7::fromBinary($row['asset_public_id'])->toString(),'status' => $row['result_status'],'version' => (int)$row['result_version']];
    }

    public function completeUploadSubmission(UuidV7 $submission, string $assetPublicId, string $status, int $version, DateTimeImmutable $now): void
    {
        $statement = $this->statement('UPDATE media_operation_receipts SET asset_public_id=:asset,result_status=:status,result_version=:version,completed_at=:now WHERE submission_public_id=:submission AND completed_at IS NULL');
        $statement->execute([':asset' => UuidV7::fromString($assetPublicId)->toBinary(),':status' => $status,':version' => $version,':now' => $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),':submission' => $submission->toBinary()]);
        if ($statement->rowCount() !== 1) {
            throw new \DomainException('Media submission completion changed concurrently.');
        }
    }

    public function enqueueScan(int $workspaceId, int $assetId, DateTimeImmutable $now): void
    {
        $time = $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
        $statement = $this->statement("INSERT INTO media_processing_jobs (public_id,workspace_id,asset_id,job_type,status,attempt,max_attempts,available_at,version,created_at,updated_at) VALUES (:public_id,:workspace,:asset,'SCAN','QUEUED',0,3,:available,1,:created,:updated)");
        $statement->execute([':public_id' => UuidV7::generate()->toBinary(),':workspace' => $workspaceId,':asset' => $assetId,':available' => $time,':created' => $time,':updated' => $time]);
    }

    public function ensurePrivateDeliveryPolicy(int $workspaceId, int $assetId, DateTimeImmutable $now): void
    {
        $time = $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
        $statement = $this->statement("INSERT INTO media_delivery_policies (workspace_id,asset_id,visibility_code,rights_granted,consent_granted,version,created_at,updated_at) VALUES (:workspace,:asset,'PRIVATE',0,0,1,:created,:updated)");
        $statement->execute([':workspace' => $workspaceId,':asset' => $assetId,':created' => $time,':updated' => $time]);
    }

    public function findDeliverable(int $workspaceId, UuidV7 $assetId): ?array
    {
        $statement = $this->statement("SELECT v.storage_object_key AS original_storage_object_key,v.mime_type AS detected_mime_type,v.byte_size,v.sha256 AS original_sha256 FROM media_assets a INNER JOIN media_delivery_policies p ON p.asset_id=a.id AND p.workspace_id=a.workspace_id INNER JOIN media_variants v ON v.asset_id=a.id AND v.workspace_id=a.workspace_id AND v.variant_code='NORMALIZED_V1' AND v.status='READY' WHERE a.workspace_id=:workspace AND a.public_id=:public_id AND a.status IN ('APPROVED','PUBLISHED') AND p.rights_granted=1 AND p.consent_granted=1 AND EXISTS (SELECT 1 FROM media_scan_results s WHERE s.workspace_id=a.workspace_id AND s.asset_id=a.id AND s.result_code='CLEAN') AND NOT EXISTS (SELECT 1 FROM media_holds h WHERE h.workspace_id=a.workspace_id AND h.asset_id=a.id AND h.released_at IS NULL) LIMIT 1");
        $statement->execute([':workspace' => $workspaceId,':public_id' => $assetId->toBinary()]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        if (!is_string($row['original_storage_object_key'] ?? null) || !is_string($row['detected_mime_type'] ?? null) || !is_string($row['original_sha256'] ?? null) || !is_numeric($row['byte_size'] ?? null) || strlen($row['original_sha256']) !== 32) {
            throw new \UnexpectedValueException('Media delivery record is malformed.');
        }
        return ['storage_key' => $row['original_storage_object_key'],'mime_type' => $row['detected_mime_type'],'byte_size' => (int)$row['byte_size'],'sha256' => $row['original_sha256']];
    }

    private function statement(string $sql): PDOStatement
    {
        $statement = $this->connections->connection()->prepare($sql);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Media statement could not be prepared.');
        }
        return $statement;
    }
}
