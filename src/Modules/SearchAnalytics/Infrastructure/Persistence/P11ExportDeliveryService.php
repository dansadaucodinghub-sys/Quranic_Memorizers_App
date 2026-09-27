<?php

declare(strict_types=1);

namespace Qmdb\Modules\SearchAnalytics\Infrastructure\Persistence;

use PDO;
use PDOStatement;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class P11ExportDeliveryService
{
    public function __construct(
        private DatabaseConnectionProvider $connections,
        private PrivateExportArtifactStore $artifacts,
    ) {
    }

    /** @return array{contents:string,media_type:string,filename:string,checksum:string} */
    public function deliver(UuidV7 $exportId, ?int $workspaceId, int $actorAccountId): array
    {
        $statement = $this->connections->connection()->prepare(<<<'SQL'
SELECT a.id AS artifact_id,a.private_storage_key,a.media_type,a.checksum,j.format_code
FROM export_jobs j
INNER JOIN report_runs r ON r.id=j.report_run_id
INNER JOIN export_artifacts a ON a.export_job_id=j.id
WHERE j.public_id=:public_id AND j.status_code='COMPLETED' AND r.status_code='COMPLETED'
 AND j.expires_at>UTC_TIMESTAMP(6) AND j.revoked_at IS NULL
 AND a.expires_at>UTC_TIMESTAMP(6) AND a.revoked_at IS NULL AND a.deleted_at IS NULL
 AND ((:workspace_id IS NULL AND r.workspace_id IS NULL) OR r.workspace_id=:workspace_match)
LIMIT 1
SQL);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Export delivery query could not be prepared.');
        }
        $statement->bindValue(':public_id', $exportId->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':workspace_id', $workspaceId, $workspaceId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':workspace_match', $workspaceId, $workspaceId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new \DomainException('Export artifact is unavailable.');
        }
        $key = $this->string($row, 'private_storage_key');
        $mediaType = $this->string($row, 'media_type');
        $format = strtolower($this->string($row, 'format_code'));
        $artifactId = $this->integer($row, 'artifact_id');
        $checksum = $row['checksum'] ?? null;
        if (!is_string($checksum) || strlen($checksum) !== 32) {
            throw new \RuntimeException('Export artifact checksum is invalid.');
        }
        $contents = $this->artifacts->read($key);
        if (!hash_equals($checksum, hash('sha256', $contents, true))) {
            throw new \RuntimeException('Export artifact integrity verification failed.');
        }
        $event = $this->connections->connection()->prepare(<<<'SQL'
INSERT INTO reporting_events
 (public_id,subject_kind,subject_id,event_code,actor_account_id,safe_metadata_json,created_at)
VALUES (:public,'EXPORT_ARTIFACT',:subject,'DOWNLOADED',:actor,JSON_OBJECT(),UTC_TIMESTAMP(6))
SQL);
        if (!$event instanceof PDOStatement) {
            throw new \RuntimeException('Export download event could not be prepared.');
        }
        $event->execute([
            ':public' => UuidV7::generate()->toBinary(),
            ':subject' => $artifactId,
            ':actor' => $actorAccountId,
        ]);
        return [
            'contents' => $contents,
            'media_type' => $mediaType,
            'filename' => 'qmdb-report-' . $exportId->toString() . '.' . $format,
            'checksum' => bin2hex($checksum),
        ];
    }

    /** @param array<array-key,mixed> $row */
    private function string(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value)) {
            throw new \RuntimeException('Export artifact metadata is invalid.');
        }
        return $value;
    }

    /** @param array<array-key,mixed> $row */
    private function integer(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        if (!is_int($value) && !is_string($value)) {
            throw new \RuntimeException('Export artifact identity is invalid.');
        }
        if (is_string($value) && preg_match('/\A[0-9]+\z/', $value) !== 1) {
            throw new \RuntimeException('Export artifact identity is invalid.');
        }
        return (int) $value;
    }
}
