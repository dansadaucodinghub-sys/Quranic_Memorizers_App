<?php

declare(strict_types=1);

namespace Qmdb\Modules\SearchAnalytics\Infrastructure\Persistence;

use PDO;
use PDOStatement;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

/** Bounded P11 reads and lifecycle writes. Raw artifact keys never leave this repository. */
final readonly class MySqlP11Repository
{
    private const array TABLES = [
        'search_projection_documents', 'search_projection_events', 'search_projection_checkpoints',
        'analytics_privacy_policies', 'analytics_metric_definitions', 'analytics_dashboard_definitions',
        'analytics_dashboard_widgets', 'analytics_snapshot_runs', 'analytics_snapshot_values',
        'analytics_snapshot_events', 'report_definitions', 'report_runs', 'export_jobs',
        'export_artifacts', 'reporting_events', 'p11_operation_receipts', 'p11_notification_intents',
    ];

    private const array MIGRATIONS = [
        '20260925100000_create_search_projection_foundation',
        '20260925101000_create_analytics_catalog',
        '20260925102000_create_analytics_snapshot_runtime',
        '20260925103000_create_reporting_export_runtime',
        '20260925104000_harden_analytics_snapshot_identity',
    ];

    public function __construct(private DatabaseConnectionProvider $connections)
    {
    }

    /** @return array{tables:int,migrations:int,permissions:int,metrics:int,reports:int} */
    public function verifyFoundation(): array
    {
        $database = $this->connections->connection();
        $tables = $database->query('SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE()');
        if (!$tables instanceof PDOStatement) {
            throw new \RuntimeException('P11 table inventory is unavailable.');
        }
        $found = $tables->fetchAll(PDO::FETCH_COLUMN);
        foreach (self::TABLES as $table) {
            if (!in_array($table, $found, true)) {
                throw new \RuntimeException('Required P11 table is missing: ' . $table);
            }
        }
        $appliedQuery = $database->query("SELECT migration_id FROM qmdb_schema_migrations WHERE status='APPLIED'");
        if (!$appliedQuery instanceof PDOStatement) {
            throw new \RuntimeException('P11 migration ledger is unavailable.');
        }
        $applied = $appliedQuery->fetchAll(PDO::FETCH_COLUMN);
        foreach (self::MIGRATIONS as $migration) {
            if (!in_array($migration, $applied, true)) {
                throw new \RuntimeException('Required P11 migration is not applied: ' . $migration);
            }
        }
        $seed = $database->query("SELECT COUNT(*) FROM qmdb_schema_seeds WHERE seed_id='20260925110000_seed_p11_search_analytics_reporting_catalog' AND status='APPLIED'");
        if (!$seed instanceof PDOStatement || (int) $seed->fetchColumn() !== 1) {
            throw new \RuntimeException('P11 catalog seed is not applied.');
        }
        $permissions = $this->count("SELECT COUNT(*) FROM authorization_permissions WHERE owning_module='search.analytics_reporting' AND status='ACTIVE'");
        $metrics = $this->count("SELECT COUNT(*) FROM analytics_metric_definitions WHERE status_code='ACTIVE'");
        $reports = $this->count("SELECT COUNT(*) FROM report_definitions WHERE status_code='ACTIVE'");
        if ($permissions !== 13 || $metrics !== 5 || $reports !== 3) {
            throw new \RuntimeException('P11 governed catalogs are incomplete.');
        }
        return ['tables' => count(self::TABLES), 'migrations' => count(self::MIGRATIONS), 'permissions' => $permissions, 'metrics' => $metrics, 'reports' => $reports];
    }

    /** @return list<array<string,mixed>> */
    public function search(string $query, ?int $workspaceId, string $scope, int $afterId, int $limit): array
    {
        $limit = max(1, min(50, $limit));
        $scopeWhere = match ($scope) {
            'PUBLIC' => "visibility_code='PUBLIC'",
            'WORKSPACE' => "(visibility_code='PUBLIC' OR (visibility_code='WORKSPACE' AND workspace_id=:workspace_id))",
            'PLATFORM' => "visibility_code IN ('PUBLIC','WORKSPACE','PLATFORM')",
            default => throw new \InvalidArgumentException('Unknown search scope.'),
        };
        $textWhere = $query === '' ? '' : ' AND MATCH(title,summary,normalized_latin,normalized_arabic) AGAINST(:query IN BOOLEAN MODE)';
        $sql = "SELECT id,BIN_TO_UUID(public_id) AS public_id,source_kind,status_code,provenance_code,locale,title,summary,source_updated_at,projected_at FROM search_projection_documents WHERE retired_at IS NULL AND {$scopeWhere} AND id>:after_id{$textWhere} ORDER BY id ASC LIMIT {$limit}";
        $statement = $this->prepare($sql);
        $parameters = [':after_id' => $afterId];
        if ($workspaceId !== null && $scope === 'WORKSPACE') {
            $parameters[':workspace_id'] = $workspaceId;
        }
        if ($query !== '') {
            $tokens = preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $parameters[':query'] = implode(' ', array_map(static fn (string $token): string => '+' . $token . '*', array_slice($tokens, 0, 8)));
        }
        $statement->execute($parameters);
        return $this->rows($statement);
    }

    /** @return list<array<string,mixed>> */
    public function dashboards(string $scope): array
    {
        $statement = $this->prepare(<<<'SQL'
SELECT BIN_TO_UUID(d.public_id) AS public_id,d.code,d.label_key,d.scope_code,d.version,
 COUNT(w.id) AS widget_count,MAX(s.completed_at) AS freshness_at
FROM analytics_dashboard_definitions d
LEFT JOIN analytics_dashboard_widgets w ON w.dashboard_definition_id=d.id
LEFT JOIN analytics_snapshot_runs s ON s.metric_definition_id=w.metric_definition_id AND s.status_code='COMPLETED'
WHERE d.status_code='ACTIVE' AND d.scope_code=:scope
GROUP BY d.id,d.public_id,d.code,d.label_key,d.scope_code,d.version
ORDER BY d.code
SQL);
        $statement->execute([':scope' => $scope]);
        return $this->rows($statement);
    }

    /** @return list<array<string,mixed>> */
    public function reportDefinitions(string $scope): array
    {
        $statement = $this->prepare(<<<'SQL'
SELECT BIN_TO_UUID(public_id) AS public_id,code,label_key,scope_code,row_limit,byte_limit,sensitive_flag,approval_required,version
FROM report_definitions WHERE status_code='ACTIVE' AND scope_code=:scope ORDER BY code
SQL);
        $statement->execute([':scope' => $scope]);
        return $this->rows($statement);
    }

    /** @return list<array<string,mixed>> */
    public function reportRuns(int $accountId, ?int $workspaceId): array
    {
        $sql = <<<'SQL'
SELECT BIN_TO_UUID(r.public_id) AS public_id,d.code,r.purpose,r.status_code,r.source_watermark,
 r.created_at,r.completed_at,r.expires_at,r.error_code,BIN_TO_UUID(j.public_id) AS export_public_id
FROM report_runs r INNER JOIN report_definitions d ON d.id=r.report_definition_id
LEFT JOIN export_jobs j ON j.report_run_id=r.id AND j.status_code='COMPLETED'
 AND j.revoked_at IS NULL AND j.expires_at>UTC_TIMESTAMP(6)
WHERE r.requester_account_id=:account_id
 AND ((:workspace_null IS NULL AND r.workspace_id IS NULL) OR r.workspace_id=:workspace_match)
ORDER BY r.id DESC LIMIT 50
SQL;
        $statement = $this->prepare($sql);
        $statement->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $statement->bindValue(':workspace_null', $workspaceId, $workspaceId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':workspace_match', $workspaceId, $workspaceId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->execute();
        return $this->rows($statement);
    }

    public function requestReport(
        int $accountId,
        ?int $workspaceId,
        UuidV7 $definitionId,
        UuidV7 $submissionId,
        string $purpose,
    ): UuidV7 {
        if ($purpose === '' || mb_strlen($purpose) > 500) {
            throw new \InvalidArgumentException('A bounded report purpose is required.');
        }
        $database = $this->connections->connection();
        $database->beginTransaction();
        try {
            $existing = $this->prepare('SELECT subject_public_id FROM p11_operation_receipts WHERE actor_account_id=:actor AND operation_code=\'REPORT_REQUEST\' AND submission_id=:submission');
            $existing->execute([':actor' => $accountId, ':submission' => $submissionId->toBinary()]);
            $found = $existing->fetchColumn();
            if (is_string($found)) {
                $database->commit();
                return UuidV7::fromBinary($found);
            }
            $definition = $this->prepare("SELECT id,scope_code,approval_required FROM report_definitions WHERE public_id=:id AND status_code='ACTIVE' FOR SHARE");
            $definition->execute([':id' => $definitionId->toBinary()]);
            $record = $this->row($definition);
            if ($record === null) {
                throw new \DomainException('Report definition is unavailable.');
            }
            if (($record['scope_code'] === 'WORKSPACE') !== ($workspaceId !== null)) {
                throw new \DomainException('Report scope does not match the active authority boundary.');
            }
            $runId = UuidV7::generate();
            $status = $this->integer($record, 'approval_required') === 1 ? 'APPROVAL_REQUIRED' : 'PENDING';
            $insert = $this->prepare(<<<'SQL'
INSERT INTO report_runs (public_id,workspace_id,report_definition_id,requester_account_id,approver_account_id,purpose,parameters_json,status_code,source_watermark,attempt_count,next_attempt_at,lease_owner,lease_expires_at,approved_at,completed_at,failed_at,revoked_at,expires_at,error_code,checksum,created_at,updated_at)
VALUES (:public_id,:workspace_id,:definition_id,:account_id,NULL,:purpose,JSON_OBJECT(),:status,DATE_FORMAT(UTC_TIMESTAMP(6),'%Y-%m-%dT%H:%i:%s.%fZ'),0,UTC_TIMESTAMP(6),NULL,NULL,NULL,NULL,NULL,NULL,DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 24 HOUR),NULL,NULL,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))
SQL);
            $insert->bindValue(':public_id', $runId->toBinary(), PDO::PARAM_LOB);
            $insert->bindValue(':workspace_id', $workspaceId, $workspaceId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $insert->bindValue(':definition_id', $this->integer($record, 'id'), PDO::PARAM_INT);
            $insert->bindValue(':account_id', $accountId, PDO::PARAM_INT);
            $insert->bindValue(':purpose', $purpose);
            $insert->bindValue(':status', $status);
            $insert->execute();
            $reportRunId = (int) $database->lastInsertId();
            $this->appendReportingEvent($reportRunId, 'CREATED', $accountId);
            if ($status === 'APPROVAL_REQUIRED') {
                $this->appendReportingEvent($reportRunId, 'APPROVAL_REQUIRED', $accountId);
            }
            $receipt = $this->prepare("INSERT INTO p11_operation_receipts (public_id,workspace_id,actor_account_id,operation_code,submission_id,request_hash,subject_public_id,response_code,created_at) VALUES (:id,:workspace,:actor,'REPORT_REQUEST',:submission,UNHEX(SHA2(:purpose,256)),:subject,:response,UTC_TIMESTAMP(6))");
            $receipt->execute([':id' => UuidV7::generate()->toBinary(), ':workspace' => $workspaceId, ':actor' => $accountId, ':submission' => $submissionId->toBinary(), ':purpose' => $purpose, ':subject' => $runId->toBinary(), ':response' => $status]);
            $database->commit();
            return $runId;
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    /** @return list<array<string,mixed>> */
    public function approvalRuns(?int $workspaceId): array
    {
        $statement = $this->prepare(<<<'SQL'
SELECT BIN_TO_UUID(r.public_id) AS public_id,d.code,r.purpose,r.created_at,r.expires_at
FROM report_runs r
INNER JOIN report_definitions d ON d.id=r.report_definition_id
WHERE r.status_code='APPROVAL_REQUIRED'
 AND ((:workspace_null IS NULL AND r.workspace_id IS NULL) OR r.workspace_id=:workspace_match)
ORDER BY r.id ASC LIMIT 50
SQL);
        $statement->bindValue(':workspace_null', $workspaceId, $workspaceId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':workspace_match', $workspaceId, $workspaceId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->execute();
        return $this->rows($statement);
    }

    public function approveReport(
        int $accountId,
        ?int $workspaceId,
        UuidV7 $reportId,
        UuidV7 $submissionId,
    ): void {
        $database = $this->connections->connection();
        $database->beginTransaction();
        try {
            $receipt = $this->prepare("SELECT id FROM p11_operation_receipts WHERE actor_account_id=:actor AND operation_code='REPORT_APPROVE' AND submission_id=:submission");
            $receipt->execute([':actor' => $accountId, ':submission' => $submissionId->toBinary()]);
            if ($receipt->fetchColumn() !== false) {
                $database->commit();
                return;
            }
            $statement = $this->prepare(<<<'SQL'
SELECT id,requester_account_id FROM report_runs
WHERE public_id=:public AND status_code='APPROVAL_REQUIRED'
 AND ((:workspace_null IS NULL AND workspace_id IS NULL) OR workspace_id=:workspace_match)
FOR UPDATE
SQL);
            $statement->bindValue(':public', $reportId->toBinary(), PDO::PARAM_LOB);
            $statement->bindValue(':workspace_null', $workspaceId, $workspaceId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $statement->bindValue(':workspace_match', $workspaceId, $workspaceId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $statement->execute();
            $run = $this->row($statement);
            if ($run === null || $this->integer($run, 'requester_account_id') === $accountId) {
                throw new \DomainException('Independent report approval is required.');
            }
            $runId = $this->integer($run, 'id');
            $this->prepare("UPDATE report_runs SET status_code='APPROVED',approver_account_id=:actor,approved_at=UTC_TIMESTAMP(6),next_attempt_at=UTC_TIMESTAMP(6),updated_at=UTC_TIMESTAMP(6) WHERE id=:id AND status_code='APPROVAL_REQUIRED'")->execute([':actor' => $accountId, ':id' => $runId]);
            $this->appendReportingEvent($runId, 'APPROVED', $accountId);
            $this->prepare("INSERT INTO p11_operation_receipts (public_id,workspace_id,actor_account_id,operation_code,submission_id,request_hash,subject_public_id,response_code,created_at) VALUES (:id,:workspace,:actor,'REPORT_APPROVE',:submission,UNHEX(SHA2('REPORT_APPROVE',256)),:subject,'APPROVED',UTC_TIMESTAMP(6))")->execute([':id' => UuidV7::generate()->toBinary(), ':workspace' => $workspaceId, ':actor' => $accountId, ':submission' => $submissionId->toBinary(), ':subject' => $reportId->toBinary()]);
            $database->commit();
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    /** @return array{key:string,type:string} */
    public function verifySearchQueryPlan(): array
    {
        $statement = $this->prepare("EXPLAIN SELECT id FROM search_projection_documents FORCE INDEX (ix_p11_search_public) WHERE visibility_code='PUBLIC' AND status_code='PUBLISHED' AND retired_at IS NULL ORDER BY projected_at DESC,id DESC LIMIT 25");
        $statement->execute();
        $plan = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($plan) || ($plan['key'] ?? null) !== 'ix_p11_search_public') {
            throw new \RuntimeException('P11 public search query is not using its bounded index.');
        }
        $type = $plan['type'] ?? '';
        if (!is_string($type)) {
            throw new \RuntimeException('P11 search access type is invalid.');
        }
        return ['key' => 'ix_p11_search_public', 'type' => $type];
    }

    public function pendingSnapshotCount(): int
    {
        return $this->count("SELECT COUNT(*) FROM analytics_snapshot_runs WHERE status_code IN ('PENDING','RETRY') AND next_attempt_at<=UTC_TIMESTAMP(6)");
    }

    public function pendingReportCount(): int
    {
        return $this->count("SELECT COUNT(*) FROM report_runs WHERE status_code IN ('PENDING','APPROVED','RETRY') AND next_attempt_at<=UTC_TIMESTAMP(6)");
    }

    public function expirableArtifactCount(): int
    {
        return $this->count('SELECT COUNT(*) FROM export_artifacts WHERE expires_at<=UTC_TIMESTAMP(6) AND deleted_at IS NULL');
    }

    private function count(string $sql): int
    {
        $statement = $this->connections->connection()->query($sql);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('P11 count query failed.');
        }
        return (int) $statement->fetchColumn();
    }

    private function appendReportingEvent(int $reportRunId, string $eventCode, int $actorAccountId): void
    {
        $statement = $this->prepare(<<<'SQL'
INSERT INTO reporting_events
 (public_id,subject_kind,subject_id,event_code,actor_account_id,safe_metadata_json,created_at)
VALUES (:public,'REPORT_RUN',:subject,:event,:actor,JSON_OBJECT(),UTC_TIMESTAMP(6))
SQL);
        $statement->execute([
            ':public' => UuidV7::generate()->toBinary(),
            ':subject' => $reportRunId,
            ':event' => $eventCode,
            ':actor' => $actorAccountId,
        ]);
    }

    private function prepare(string $sql): PDOStatement
    {
        $statement = $this->connections->connection()->prepare($sql);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('P11 database statement could not be prepared.');
        }
        return $statement;
    }

    /** @return array<string,mixed>|null */
    private function row(PDOStatement $statement): ?array
    {
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        if (!is_array($row)) {
            throw new \RuntimeException('P11 database row is invalid.');
        }
        $normalized = [];
        foreach ($row as $key => $value) {
            if (!is_string($key)) {
                throw new \RuntimeException('P11 database column is invalid.');
            }
            $normalized[$key] = $value;
        }
        return $normalized;
    }

    /** @return list<array<string,mixed>> */
    private function rows(PDOStatement $statement): array
    {
        $rows = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            if (!is_array($row)) {
                throw new \RuntimeException('P11 database row is invalid.');
            }
            $normalized = [];
            foreach ($row as $key => $value) {
                if (!is_string($key)) {
                    throw new \RuntimeException('P11 database column is invalid.');
                }
                $normalized[$key] = $value;
            }
            $rows[] = $normalized;
        }
        return $rows;
    }

    /** @param array<string,mixed> $row */
    private function integer(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        if (!is_int($value) && !is_string($value)) {
            throw new \RuntimeException('P11 integer column is invalid: ' . $key);
        }
        if (is_string($value) && preg_match('/\A[0-9]+\z/', $value) !== 1) {
            throw new \RuntimeException('P11 integer column is invalid: ' . $key);
        }
        return (int) $value;
    }
}
