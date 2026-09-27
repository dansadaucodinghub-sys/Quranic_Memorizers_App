<?php

declare(strict_types=1);

namespace Qmdb\Modules\SearchAnalytics\Infrastructure\Persistence;

use PDO;
use PDOStatement;
use Qmdb\Modules\SearchAnalytics\Domain\ArabicSearchNormalizer;
use Qmdb\Modules\SearchAnalytics\Domain\DeterministicExportFormatter;
use Qmdb\Modules\SearchAnalytics\Domain\PrivacyDisclosurePolicy;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class P11MaintenanceService
{
    public function __construct(
        private DatabaseConnectionProvider $connections,
        private PrivacyDisclosurePolicy $privacy,
        private DeterministicExportFormatter $formatter,
        private PrivateExportArtifactStore $artifacts,
        private ArabicSearchNormalizer $normalizer,
    ) {
    }

    /** @return array{examined:int,changed:int} */
    public function processSnapshots(bool $dryRun, int $limit = 25): array
    {
        $limit = max(1, min(100, $limit));
        $due = $this->scalar("SELECT COUNT(*) FROM analytics_snapshot_runs WHERE status_code IN ('PENDING','RETRY') AND next_attempt_at<=UTC_TIMESTAMP(6)");
        if ($dryRun) {
            return ['examined' => min($due, $limit), 'changed' => 0];
        }
        $changed = 0;
        while ($changed < $limit && $this->processOneSnapshot()) {
            $changed++;
        }
        return ['examined' => min($due, $limit), 'changed' => $changed];
    }

    /** @return array{examined:int,changed:int} */
    public function rebuildSnapshots(bool $dryRun): array
    {
        $candidateStatement = $this->prepare(<<<'SQL'
SELECT m.id AS metric_id,NULL AS workspace_id
FROM analytics_metric_definitions m
WHERE m.status_code='ACTIVE' AND m.scope_code IN ('PUBLIC','PLATFORM')
UNION ALL
SELECT m.id AS metric_id,w.id AS workspace_id
FROM analytics_metric_definitions m
CROSS JOIN workspaces w
WHERE m.status_code='ACTIVE' AND m.scope_code='WORKSPACE' AND w.status_code='ACTIVE'
ORDER BY metric_id,workspace_id
SQL);
        $candidateStatement->execute();
        $candidates = $this->rows($candidateStatement);
        $candidateCount = count($candidates);
        if ($dryRun) {
            return ['examined' => $candidateCount, 'changed' => 0];
        }
        $changed = 0;
        foreach ($candidates as $candidate) {
            $statement = $this->prepare(<<<'SQL'
INSERT IGNORE INTO analytics_snapshot_runs
 (public_id,workspace_id,metric_definition_id,period_start,period_end,source_watermark,status_code,
 attempt_count,next_attempt_at,lease_owner,lease_expires_at,completed_at,failed_at,error_code,checksum,
 created_at,updated_at)
VALUES (:public,:workspace,:metric,UTC_DATE(),DATE_ADD(UTC_DATE(),INTERVAL 1 DAY),:watermark,'PENDING',
 0,UTC_TIMESTAMP(6),NULL,NULL,NULL,NULL,NULL,NULL,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))
SQL);
            $statement->bindValue(':public', UuidV7::generate()->toBinary(), PDO::PARAM_LOB);
            $workspaceId = $candidate['workspace_id'];
            $statement->bindValue(
                ':workspace',
                $workspaceId,
                $workspaceId === null ? PDO::PARAM_NULL : PDO::PARAM_INT,
            );
            $statement->bindValue(':metric', (int) $candidate['metric_id'], PDO::PARAM_INT);
            $statement->bindValue(':watermark', 'daily:' . gmdate('Y-m-d'));
            $statement->execute();
            if ($statement->rowCount() === 1) {
                $runId = (int) $this->connections->connection()->lastInsertId();
                $this->appendSnapshotEvent($runId, 'CREATED');
                $changed++;
            }
        }
        return ['examined' => $candidateCount, 'changed' => $changed];
    }

    /** @return array{examined:int,changed:int} */
    public function reconcileSnapshots(): array
    {
        $statement = $this->prepare("UPDATE analytics_snapshot_runs SET status_code='RETRY',lease_owner=NULL,lease_expires_at=NULL,next_attempt_at=UTC_TIMESTAMP(6),updated_at=UTC_TIMESTAMP(6) WHERE status_code='LEASED' AND lease_expires_at<UTC_TIMESTAMP(6) LIMIT 100");
        $statement->execute();
        return ['examined' => $statement->rowCount(), 'changed' => $statement->rowCount()];
    }

    /** @return array{examined:int,changed:int} */
    public function rebuildSearch(bool $dryRun): array
    {
        $source = $this->prepare(<<<'SQL'
SELECT 'COMPETITION_RESULT' AS source_kind,p.public_id AS source_public_id,p.workspace_id,
 p.version AS source_version,IF(p.public_visibility='PUBLIC','PUBLIC','WORKSPACE') AS visibility_code,
 p.status AS status_code,'en' AS locale,
 CONCAT('Competition result publication ',p.publication_number) AS title,
 CONCAT('Official competition result status: ',p.status) AS summary,p.updated_at AS source_updated_at
FROM competition_result_publications p
WHERE p.status NOT IN ('WITHDRAWN','SUPERSEDED','ARCHIVED')
UNION ALL
SELECT 'CERTIFICATE',c.public_id,c.workspace_id,c.version,'WORKSPACE',c.status,'en',
 CONCAT('Certificate ',c.certificate_number),CONCAT('Certificate status: ',c.status),c.updated_at
FROM certificates c WHERE c.status<>'PREPARED'
UNION ALL
SELECT 'RECITATION_CLIP',c.public_id,c.workspace_id,c.version,
 IF(c.audience='PUBLIC','PUBLIC','WORKSPACE'),c.status,
 IF(c.caption_language='ar','ar','en'),'Recitation clip',LEFT(c.caption,600),c.updated_at
FROM recitation_clips c WHERE c.status='PUBLISHED'
ORDER BY source_kind,source_public_id
SQL);
        $source->execute();
        $rows = $this->rows($source);
        if ($dryRun) {
            return ['examined' => count($rows), 'changed' => 0];
        }
        $database = $this->connections->connection();
        $database->beginTransaction();
        try {
            $changed = 0;
            foreach ($rows as $row) {
                $changed += $this->upsertSearchDocument($row);
            }
            $changed += $this->retireUnavailableSearchDocuments();
            $checkpoint = $this->prepare(<<<'SQL'
INSERT INTO search_projection_checkpoints
 (projection_code,last_event_id,schema_version,rebuilt_at,updated_at)
VALUES ('authoritative-search-v1',0,1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))
ON DUPLICATE KEY UPDATE rebuilt_at=VALUES(rebuilt_at),updated_at=VALUES(updated_at)
SQL);
            $checkpoint->execute();
            $database->commit();
            return ['examined' => count($rows), 'changed' => $changed];
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    /** @return array{examined:int,changed:int} */
    public function reconcileSearch(): array
    {
        $stale = $this->scalar(<<<'SQL'
SELECT COUNT(*) FROM search_projection_documents d
WHERE d.retired_at IS NULL AND (
 (d.source_kind='COMPETITION_RESULT' AND NOT EXISTS (
  SELECT 1 FROM competition_result_publications p WHERE p.public_id=d.source_public_id
   AND p.status NOT IN ('WITHDRAWN','SUPERSEDED','ARCHIVED')
 )) OR
 (d.source_kind='CERTIFICATE' AND NOT EXISTS (
  SELECT 1 FROM certificates c WHERE c.public_id=d.source_public_id AND c.status<>'PREPARED'
 )) OR
 (d.source_kind='RECITATION_CLIP' AND NOT EXISTS (
  SELECT 1 FROM recitation_clips c WHERE c.public_id=d.source_public_id AND c.status='PUBLISHED'
 )))
SQL);
        return ['examined' => $stale, 'changed' => 0];
    }

    /** @return array{examined:int,changed:int} */
    public function processReports(bool $dryRun, int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));
        $due = $this->scalar("SELECT COUNT(*) FROM report_runs WHERE status_code IN ('PENDING','APPROVED','RETRY') AND next_attempt_at<=UTC_TIMESTAMP(6)");
        if ($dryRun) {
            return ['examined' => min($due, $limit), 'changed' => 0];
        }
        $changed = 0;
        while ($changed < $limit && $this->processOneReport()) {
            $changed++;
        }
        return ['examined' => min($due, $limit), 'changed' => $changed];
    }

    /** @return array{examined:int,changed:int} */
    public function reconcileReports(): array
    {
        $statement = $this->prepare("UPDATE report_runs SET status_code='RETRY',lease_owner=NULL,lease_expires_at=NULL,next_attempt_at=UTC_TIMESTAMP(6),updated_at=UTC_TIMESTAMP(6) WHERE status_code='LEASED' AND lease_expires_at<UTC_TIMESTAMP(6) LIMIT 100");
        $statement->execute();
        return ['examined' => $statement->rowCount(), 'changed' => $statement->rowCount()];
    }

    /** @return array{examined:int,changed:int} */
    public function cleanupExports(bool $dryRun, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $statement = $this->prepare("SELECT id,private_storage_key FROM export_artifacts WHERE expires_at<=UTC_TIMESTAMP(6) AND deleted_at IS NULL ORDER BY id LIMIT {$limit}");
        $statement->execute();
        $rows = $this->rows($statement);
        if ($dryRun) {
            return ['examined' => count($rows), 'changed' => 0];
        }
        $changed = 0;
        foreach ($rows as $row) {
            $this->artifacts->delete((string) $row['private_storage_key']);
            $update = $this->prepare('UPDATE export_artifacts SET deleted_at=UTC_TIMESTAMP(6) WHERE id=:id AND deleted_at IS NULL');
            $update->execute([':id' => (int) $row['id']]);
            $changed += $update->rowCount();
        }
        return ['examined' => count($rows), 'changed' => $changed];
    }

    private function processOneSnapshot(): bool
    {
        $database = $this->connections->connection();
        $database->beginTransaction();
        try {
            $claim = $database->query(<<<'SQL'
SELECT r.id,r.workspace_id,m.formula_code,m.scope_code,p.policy_type,p.minimum_cell_size,p.rounding_bucket
FROM analytics_snapshot_runs r
INNER JOIN analytics_metric_definitions m ON m.id=r.metric_definition_id
INNER JOIN analytics_privacy_policies p ON p.id=m.privacy_policy_id
WHERE r.status_code IN ('PENDING','RETRY') AND r.next_attempt_at<=UTC_TIMESTAMP(6)
ORDER BY r.id LIMIT 1 FOR UPDATE SKIP LOCKED
SQL);
            $row = $this->row($claim);
            if ($row === null) {
                $database->commit();
                return false;
            }
            $lease = UuidV7::generate()->toBinary();
            $this->prepare("UPDATE analytics_snapshot_runs SET status_code='LEASED',lease_owner=:lease,lease_expires_at=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 120 SECOND),attempt_count=attempt_count+1,updated_at=UTC_TIMESTAMP(6) WHERE id=:id")->execute([':lease' => $lease, ':id' => (int) $row['id']]);
            $this->appendSnapshotEvent((int) $row['id'], 'CLAIMED');
            $rawCount = $this->metricCount((string) $row['formula_code'], $row['workspace_id'] === null ? null : (int) $row['workspace_id']);
            $disclosure = $this->privacy->disclose($rawCount, (string) $row['policy_type'], (int) $row['minimum_cell_size'], (int) $row['rounding_bucket'], (string) $row['scope_code'] === 'PLATFORM');
            $checksum = hash('sha256', 'all|' . $rawCount . '|' . ($disclosure['value'] ?? 'suppressed'), true);
            $this->prepare("INSERT INTO analytics_snapshot_values (snapshot_run_id,dimension_key,dimension_json,raw_count,disclosed_value,suppression_code,checksum,created_at) VALUES (:run,'all',JSON_OBJECT(),:raw,:value,:suppression,:checksum,UTC_TIMESTAMP(6))")->execute([':run' => (int) $row['id'], ':raw' => $rawCount, ':value' => $disclosure['value'], ':suppression' => $disclosure['suppression_code'], ':checksum' => $checksum]);
            $this->prepare("UPDATE analytics_snapshot_runs SET status_code='COMPLETED',lease_owner=NULL,lease_expires_at=NULL,completed_at=UTC_TIMESTAMP(6),checksum=:checksum,updated_at=UTC_TIMESTAMP(6) WHERE id=:id")->execute([':checksum' => $checksum, ':id' => (int) $row['id']]);
            $this->appendSnapshotEvent((int) $row['id'], 'COMPLETED');
            $database->commit();
            return true;
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    /** @param array<string,scalar|null> $row */
    private function upsertSearchDocument(array $row): int
    {
        $sourceId = $row['source_public_id'] ?? null;
        if (!is_string($sourceId)) {
            throw new \RuntimeException('Search source identity is invalid.');
        }
        $title = (string) $row['title'];
        $summary = (string) $row['summary'];
        $normalized = $this->normalizer->normalize($title . ' ' . $summary);
        $payload = json_encode(
            ['source_id' => UuidV7::fromBinary($sourceId)->toString()],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
        $checksum = hash('sha256', implode('|', [
            (string) $row['source_kind'],
            (string) $row['source_version'],
            (string) $row['visibility_code'],
            (string) $row['status_code'],
            $title,
            $summary,
        ]), true);
        $statement = $this->prepare(<<<'SQL'
INSERT INTO search_projection_documents
 (public_id,workspace_id,source_kind,source_public_id,source_version,visibility_code,status_code,
 provenance_code,locale,title,summary,normalized_latin,normalized_arabic,safe_payload_json,schema_version,
 source_updated_at,projected_at,checksum,retired_at)
VALUES (:public,:workspace,:kind,:source,:version,:visibility,:status,'AUTHORITATIVE',:locale,:title,:summary,
 :latin,:arabic,:payload,1,:source_updated,UTC_TIMESTAMP(6),:checksum,NULL)
ON DUPLICATE KEY UPDATE source_version=VALUES(source_version),visibility_code=VALUES(visibility_code),
 status_code=VALUES(status_code),locale=VALUES(locale),title=VALUES(title),summary=VALUES(summary),
 normalized_latin=VALUES(normalized_latin),normalized_arabic=VALUES(normalized_arabic),
 safe_payload_json=VALUES(safe_payload_json),source_updated_at=VALUES(source_updated_at),
 projected_at=VALUES(projected_at),checksum=VALUES(checksum),retired_at=NULL
SQL);
        $statement->bindValue(':public', UuidV7::generate()->toBinary(), PDO::PARAM_LOB);
        $workspaceId = $row['workspace_id'];
        $statement->bindValue(':workspace', $workspaceId, $workspaceId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':kind', (string) $row['source_kind']);
        $statement->bindValue(':source', $sourceId, PDO::PARAM_LOB);
        $statement->bindValue(':version', (int) $row['source_version'], PDO::PARAM_INT);
        $statement->bindValue(':visibility', (string) $row['visibility_code']);
        $statement->bindValue(':status', (string) $row['status_code']);
        $statement->bindValue(':locale', (string) $row['locale']);
        $statement->bindValue(':title', $title);
        $statement->bindValue(':summary', $summary);
        $statement->bindValue(':latin', $normalized);
        $statement->bindValue(':arabic', $normalized);
        $statement->bindValue(':payload', $payload);
        $statement->bindValue(':source_updated', (string) $row['source_updated_at']);
        $statement->bindValue(':checksum', $checksum, PDO::PARAM_LOB);
        $statement->execute();
        return $statement->rowCount() > 0 ? 1 : 0;
    }

    private function retireUnavailableSearchDocuments(): int
    {
        $statement = $this->prepare(<<<'SQL'
UPDATE search_projection_documents d SET d.retired_at=UTC_TIMESTAMP(6)
WHERE d.retired_at IS NULL AND (
 (d.source_kind='COMPETITION_RESULT' AND NOT EXISTS (
  SELECT 1 FROM competition_result_publications p WHERE p.public_id=d.source_public_id
   AND p.status NOT IN ('WITHDRAWN','SUPERSEDED','ARCHIVED')
 )) OR
 (d.source_kind='CERTIFICATE' AND NOT EXISTS (
  SELECT 1 FROM certificates c WHERE c.public_id=d.source_public_id AND c.status<>'PREPARED'
 )) OR
 (d.source_kind='RECITATION_CLIP' AND NOT EXISTS (
  SELECT 1 FROM recitation_clips c WHERE c.public_id=d.source_public_id AND c.status='PUBLISHED'
 )))
SQL);
        $statement->execute();
        return $statement->rowCount();
    }

    private function processOneReport(): bool
    {
        $database = $this->connections->connection();
        $database->beginTransaction();
        try {
            $claim = $database->query(<<<'SQL'
SELECT r.id,r.public_id,r.workspace_id,r.requester_account_id,r.expires_at,d.query_code,d.row_limit,d.byte_limit
FROM report_runs r INNER JOIN report_definitions d ON d.id=r.report_definition_id
WHERE r.status_code IN ('PENDING','APPROVED','RETRY') AND r.next_attempt_at<=UTC_TIMESTAMP(6)
ORDER BY r.id LIMIT 1 FOR UPDATE SKIP LOCKED
SQL);
            $run = $this->row($claim);
            if ($run === null) {
                $database->commit();
                return false;
            }
            $lease = UuidV7::generate()->toBinary();
            $this->prepare("UPDATE report_runs SET status_code='LEASED',lease_owner=:lease,lease_expires_at=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 120 SECOND),attempt_count=attempt_count+1,updated_at=UTC_TIMESTAMP(6) WHERE id=:id")->execute([':lease' => $lease, ':id' => (int) $run['id']]);
            $this->appendReportingEvent('REPORT_RUN', (int) $run['id'], 'CLAIMED', (int) $run['requester_account_id']);
            [$columns, $rows] = $this->reportRows((string) $run['query_code'], $run['workspace_id'] === null ? null : (int) $run['workspace_id'], (int) $run['row_limit']);
            $contents = $this->formatter->csv($rows, $columns);
            if (strlen($contents) > (int) $run['byte_limit']) {
                throw new \RuntimeException('REPORT_BYTE_LIMIT_EXCEEDED');
            }
            $stored = $this->artifacts->write($contents, 'csv');
            $exportId = UuidV7::generate();
            $this->prepare("INSERT INTO export_jobs (public_id,report_run_id,requester_account_id,format_code,status_code,attempt_count,next_attempt_at,lease_owner,lease_expires_at,completed_at,failed_at,revoked_at,expires_at,error_code,created_at,updated_at) VALUES (:public,:run,:requester,'CSV','COMPLETED',1,UTC_TIMESTAMP(6),NULL,NULL,UTC_TIMESTAMP(6),NULL,NULL,:expires,NULL,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))")->execute([':public' => $exportId->toBinary(), ':run' => (int) $run['id'], ':requester' => (int) $run['requester_account_id'], ':expires' => $run['expires_at']]);
            $exportJobId = (int) $database->lastInsertId();
            $this->appendReportingEvent('EXPORT_JOB', $exportJobId, 'COMPLETED', (int) $run['requester_account_id']);
            $this->prepare("INSERT INTO export_artifacts (public_id,export_job_id,private_storage_key,media_type,row_count,byte_count,checksum,created_at,expires_at,revoked_at,deleted_at) VALUES (:public,:job,:storage,'text/csv',:rows,:bytes,:checksum,UTC_TIMESTAMP(6),:expires,NULL,NULL)")->execute([':public' => UuidV7::generate()->toBinary(), ':job' => $exportJobId, ':storage' => $stored['key'], ':rows' => count($rows), ':bytes' => $stored['bytes'], ':checksum' => $stored['checksum'], ':expires' => $run['expires_at']]);
            $artifactId = (int) $database->lastInsertId();
            $this->appendReportingEvent('EXPORT_ARTIFACT', $artifactId, 'COMPLETED', (int) $run['requester_account_id']);
            $checksum = hash('sha256', (string) $run['public_id'] . $stored['checksum'], true);
            $this->prepare("UPDATE report_runs SET status_code='COMPLETED',lease_owner=NULL,lease_expires_at=NULL,completed_at=UTC_TIMESTAMP(6),checksum=:checksum,updated_at=UTC_TIMESTAMP(6) WHERE id=:id")->execute([':checksum' => $checksum, ':id' => (int) $run['id']]);
            $this->appendReportingEvent('REPORT_RUN', (int) $run['id'], 'COMPLETED', (int) $run['requester_account_id']);
            $this->prepare("INSERT INTO p11_notification_intents (public_id,recipient_account_id,type_code,subject_public_id,subject_version,safe_state_code,status_code,attempt_count,next_attempt_at,lease_owner,lease_expires_at,delivered_at,last_error_code,created_at,updated_at) VALUES (:public,:recipient,'REPORT_COMPLETED',:subject,1,'READY','PENDING',0,UTC_TIMESTAMP(6),NULL,NULL,NULL,NULL,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))")->execute([':public' => UuidV7::generate()->toBinary(), ':recipient' => (int) $run['requester_account_id'], ':subject' => $run['public_id']]);
            $database->commit();
            return true;
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    private function metricCount(string $formula, ?int $workspaceId): int
    {
        [$sql, $workspaceRequired] = match ($formula) {
            'COUNT_SEARCH_DOCUMENTS' => ['SELECT COUNT(*) FROM search_projection_documents WHERE retired_at IS NULL' . ($workspaceId === null ? '' : ' AND workspace_id=:workspace'), $workspaceId !== null],
            'COUNT_PUBLISHED_RESULTS' => ["SELECT COUNT(*) FROM competition_result_publications WHERE status='FINALIZED' AND public_visibility='PUBLIC'", false],
            'COUNT_ISSUED_CERTIFICATES' => ["SELECT COUNT(*) FROM certificates WHERE status='ISSUED'", false],
            'COUNT_PUBLISHED_CLIPS' => ["SELECT COUNT(*) FROM recitation_clips WHERE status='PUBLISHED' AND audience='PUBLIC'", false],
            default => throw new \RuntimeException('Unsupported metric formula.'),
        };
        $statement = $this->prepare($sql);
        $statement->execute($workspaceRequired ? [':workspace' => $workspaceId] : []);
        return (int) $statement->fetchColumn();
    }

    /** @return array{list<string>,list<array<string,scalar|null>>} */
    private function reportRows(string $queryCode, ?int $workspaceId, int $limit): array
    {
        [$columns, $sql, $parameters] = match ($queryCode) {
            'WORKSPACE_SEARCH_INVENTORY' => [['record_type', 'record_status', 'record_count'], 'SELECT source_kind AS record_type,status_code AS record_status,COUNT(*) AS record_count FROM search_projection_documents WHERE workspace_id=:workspace AND retired_at IS NULL GROUP BY source_kind,status_code ORDER BY source_kind,status_code LIMIT ' . $limit, [':workspace' => $workspaceId]],
            'WORKSPACE_RESULTS_SUMMARY' => [['result_status', 'record_count'], 'SELECT status AS result_status,COUNT(*) AS record_count FROM competition_result_publications WHERE workspace_id=:workspace GROUP BY status ORDER BY status LIMIT ' . $limit, [':workspace' => $workspaceId]],
            'NATIONAL_AGGREGATE_SUMMARY' => [['metric_code', 'disclosed_value', 'period_end'], "SELECT m.code AS metric_code,v.disclosed_value,r.period_end FROM analytics_snapshot_runs r INNER JOIN analytics_metric_definitions m ON m.id=r.metric_definition_id INNER JOIN analytics_snapshot_values v ON v.snapshot_run_id=r.id WHERE r.status_code='COMPLETED' AND m.scope_code='PUBLIC' ORDER BY m.code,r.period_end DESC LIMIT " . $limit, []],
            default => throw new \RuntimeException('Unsupported report query.'),
        };
        $statement = $this->prepare($sql);
        $statement->execute($parameters);
        return [$columns, $this->rows($statement)];
    }

    private function scalar(string $sql): int
    {
        $statement = $this->connections->connection()->query($sql);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('P11 scalar query failed.');
        }
        return (int) $statement->fetchColumn();
    }

    private function appendSnapshotEvent(int $snapshotRunId, string $eventCode): void
    {
        $this->prepare(<<<'SQL'
INSERT INTO analytics_snapshot_events
 (public_id,snapshot_run_id,event_code,safe_metadata_json,created_at)
VALUES (:public,:run,:event,JSON_OBJECT(),UTC_TIMESTAMP(6))
SQL)->execute([
            ':public' => UuidV7::generate()->toBinary(),
            ':run' => $snapshotRunId,
            ':event' => $eventCode,
        ]);
    }

    private function appendReportingEvent(
        string $subjectKind,
        int $subjectId,
        string $eventCode,
        ?int $actorAccountId,
    ): void {
        $statement = $this->prepare(<<<'SQL'
INSERT INTO reporting_events
 (public_id,subject_kind,subject_id,event_code,actor_account_id,safe_metadata_json,created_at)
VALUES (:public,:kind,:subject,:event,:actor,JSON_OBJECT(),UTC_TIMESTAMP(6))
SQL);
        $statement->bindValue(':public', UuidV7::generate()->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':kind', $subjectKind);
        $statement->bindValue(':subject', $subjectId, PDO::PARAM_INT);
        $statement->bindValue(':event', $eventCode);
        $statement->bindValue(
            ':actor',
            $actorAccountId,
            $actorAccountId === null ? PDO::PARAM_NULL : PDO::PARAM_INT,
        );
        $statement->execute();
    }

    private function prepare(string $sql): PDOStatement
    {
        $statement = $this->connections->connection()->prepare($sql);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('P11 database statement could not be prepared.');
        }
        return $statement;
    }

    /** @return array<string,scalar|null>|null */
    private function row(PDOStatement|false $statement): ?array
    {
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('P11 database query failed.');
        }
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        if (!is_array($row)) {
            throw new \RuntimeException('P11 database row is invalid.');
        }
        return $this->scalarRow($row);
    }

    /** @return list<array<string,scalar|null>> */
    private function rows(PDOStatement $statement): array
    {
        $rows = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            if (!is_array($row)) {
                throw new \RuntimeException('P11 database row is invalid.');
            }
            $rows[] = $this->scalarRow($row);
        }
        return $rows;
    }

    /**
     * @param array<array-key,mixed> $row
     * @return array<string,scalar|null>
     */
    private function scalarRow(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            if (!is_string($key) || (!is_scalar($value) && $value !== null)) {
                throw new \RuntimeException('P11 database value is invalid.');
            }
            $normalized[$key] = $value;
        }
        return $normalized;
    }
}
