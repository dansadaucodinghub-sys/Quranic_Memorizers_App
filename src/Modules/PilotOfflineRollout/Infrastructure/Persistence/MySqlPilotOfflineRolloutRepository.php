<?php

declare(strict_types=1);

namespace Qmdb\Modules\PilotOfflineRollout\Infrastructure\Persistence;

use PDO;
use PDOStatement;
use Qmdb\Modules\PilotOfflineRollout\Application\OfflineOperationContext;
use Qmdb\Modules\PilotOfflineRollout\Application\OfflineOperationDispatcher;
use Qmdb\Modules\PilotOfflineRollout\Application\OfflineOperationResult;
use Qmdb\Modules\PilotOfflineRollout\Domain\CanonicalJson;
use Qmdb\Modules\PilotOfflineRollout\Domain\OfflineOperationPolicy;
use Qmdb\Modules\PilotOfflineRollout\Domain\OfflinePackageCryptography;
use Qmdb\Modules\PilotOfflineRollout\Domain\PilotRolloutLifecycle;
use Qmdb\Modules\SecurityAuthorization\Domain\PilotOfflineRolloutP13AuthorizationCatalog;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Database\Transaction\TransactionOptions;
use Qmdb\Shared\Database\Transaction\TransactionRetryPolicy;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class MySqlPilotOfflineRolloutRepository
{
    private const array TABLES = [
        'feature_flags', 'feature_flag_versions', 'configuration_versions', 'operational_announcements',
        'background_job_ledger', 'dead_letter_records', 'venue_edge_node_registrations',
        'offline_assignment_packages', 'offline_submission_events', 'synchronization_batches',
        'synchronization_conflicts', 'venue_reconciliation_reports', 'pilot_programs', 'pilot_sites',
        'pilot_site_readiness_checks', 'pilot_runs', 'pilot_events', 'pilot_incidents', 'pilot_findings',
        'rollout_plans', 'rollout_waves', 'rollout_wave_assignments', 'rollout_decisions', 'rollout_events',
        'rollout_health_snapshots', 'offline_device_keys', 'offline_device_events', 'offline_package_entities',
        'offline_package_artifacts', 'offline_package_events', 'offline_sync_changes', 'offline_sync_receipts',
        'offline_conflict_assignments', 'offline_conflict_decisions', 'offline_operation_events',
        'offline_device_nonces', 'p13_operation_receipts',
    ];

    private const array MIGRATIONS = [
        '20260928100000_create_offline_platform_foundation',
        '20260928101000_create_pilot_rollout_control_plane',
        '20260928102000_extend_secure_offline_runtime',
    ];

    public function __construct(
        private DatabaseConnectionProvider $connections,
        private CanonicalJson $canonicalJson,
        private OfflinePackageCryptography $cryptography,
        private OfflineOperationPolicy $operations,
        private PilotRolloutLifecycle $lifecycle,
        private OfflineOperationDispatcher $dispatcher,
        private OfflineAuthoritativeContextFactory $contextFactory,
        private TransactionManager $transactions,
    ) {
    }

    /** @return array{tables:int,migrations:int,permissions:int,roles:int,flags:int,configurations:int,templates:int} */
    public function verifyFoundation(): array
    {
        $tables = $this->column('SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE()');
        foreach (self::TABLES as $table) {
            if (!in_array($table, $tables, true)) {
                throw new \RuntimeException('Required P13 table is missing: ' . $table);
            }
        }
        $applied = $this->column("SELECT migration_id FROM qmdb_schema_migrations WHERE status='APPLIED'");
        foreach (self::MIGRATIONS as $migration) {
            if (!in_array($migration, $applied, true)) {
                throw new \RuntimeException('Required P13 migration is not applied: ' . $migration);
            }
        }
        if ($this->count("SELECT COUNT(*) FROM qmdb_schema_seeds WHERE seed_id='20260928110000_seed_p13_pilot_offline_rollout_catalog' AND status='APPLIED'") !== 1) {
            throw new \RuntimeException('P13 governed catalog seed is not applied.');
        }
        $result = [
            'tables' => count(self::TABLES),
            'migrations' => count(self::MIGRATIONS),
            'permissions' => $this->count("SELECT COUNT(*) FROM authorization_permissions WHERE owning_module='pilot.offline_rollout' AND status='ACTIVE'"),
            'roles' => $this->count("SELECT COUNT(*) FROM authorization_roles WHERE code LIKE 'workspace.offline_%' OR code LIKE 'platform.pilot_%' OR code LIKE 'platform.rollout_%'"),
            'flags' => $this->count("SELECT COUNT(*) FROM feature_flags WHERE owning_module='pilot.offline_rollout' AND status_code='ACTIVE'"),
            'configurations' => $this->count("SELECT COUNT(*) FROM configuration_versions WHERE configuration_code LIKE 'P13\\_%' AND status_code='ACTIVE'"),
            'templates' => $this->count("SELECT COUNT(*) FROM notification_templates WHERE template_code LIKE 'P13\\_%' AND status_code='ACTIVE'"),
        ];
        if (
            $result['permissions'] !== count(PilotOfflineRolloutP13AuthorizationCatalog::PERMISSIONS)
            || $result['roles'] !== count(PilotOfflineRolloutP13AuthorizationCatalog::ROLES)
            || $result['flags'] !== 3 || $result['configurations'] !== 4 || $result['templates'] !== 12
        ) {
            throw new \RuntimeException('P13 governed catalogs are incomplete: ' . json_encode($result, JSON_THROW_ON_ERROR));
        }

        return $result;
    }

    /** @return array<string,int> */
    public function summary(?int $workspaceId): array
    {
        $workspace = $workspaceId === null ? '' : ' WHERE workspace_id=' . $workspaceId;

        return [
            'pilots_open' => $this->count("SELECT COUNT(*) FROM pilot_programs WHERE status_code NOT IN ('COMPLETED','CANCELLED','FAILED')"),
            'rollout_waves_open' => $this->count("SELECT COUNT(*) FROM rollout_waves WHERE status_code NOT IN ('COMPLETED','CANCELLED','FAILED')"),
            'devices' => $workspaceId === null ? 0 : $this->count('SELECT COUNT(*) FROM venue_edge_node_registrations' . $workspace),
            'packages_current' => $workspaceId === null ? 0 : $this->count("SELECT COUNT(*) FROM offline_assignment_packages{$workspace} AND status_code IN ('READY','DOWNLOADED','ACTIVE')"),
            'sync_open' => $workspaceId === null ? 0 : $this->count("SELECT COUNT(*) FROM synchronization_batches{$workspace} AND status_code NOT IN ('COMPLETED','FAILED','EXPIRED')"),
            'conflicts_open' => $workspaceId === null ? 0 : $this->count("SELECT COUNT(*) FROM synchronization_conflicts{$workspace} AND status_code IN ('OPEN','ASSIGNED','UNDER_REVIEW')"),
        ];
    }

    /** @return list<array<string,mixed>> */
    public function pilots(): array
    {
        return $this->queryRows('SELECT BIN_TO_UUID(public_id) AS public_id,program_code,name,scope_type,status_code,version,planned_start_at,planned_end_at FROM pilot_programs ORDER BY created_at DESC,id DESC LIMIT 100');
    }

    /** @return list<array<string,mixed>> */
    public function rollouts(): array
    {
        return $this->queryRows('SELECT BIN_TO_UUID(public_id) AS public_id,rollout_code,name,scope_type,maximum_wave_size,status_code,version FROM rollout_plans ORDER BY created_at DESC,id DESC LIMIT 100');
    }

    /** @return list<array<string,mixed>> */
    public function rolloutWaves(): array
    {
        return $this->queryRows('SELECT BIN_TO_UUID(w.public_id) AS public_id,BIN_TO_UUID(p.public_id) AS rollout_public_id,p.rollout_code,w.wave_number,w.name,w.status_code,w.assignment_count,w.version,w.planned_start_at,w.planned_end_at FROM rollout_waves w INNER JOIN rollout_plans p ON p.id=w.rollout_plan_id ORDER BY w.planned_start_at DESC,w.id DESC LIMIT 100');
    }

    /** @return list<array<string,mixed>> */
    public function devices(int $workspaceId): array
    {
        return $this->queryRows('SELECT BIN_TO_UUID(public_id) AS public_id,installation_code,display_name,device_type,platform_code,status_code,version,last_seen_at,last_sync_at FROM venue_edge_node_registrations WHERE workspace_id=:workspace ORDER BY created_at DESC,id DESC LIMIT 100', [':workspace' => $workspaceId]);
    }

    /** @return list<array<string,mixed>> */
    public function packages(int $workspaceId): array
    {
        return $this->queryRows('SELECT BIN_TO_UUID(public_id) AS public_id,package_code,status_code,schema_version,scope_version,byte_size,version,generated_at,expires_at FROM offline_assignment_packages WHERE workspace_id=:workspace ORDER BY created_at DESC,id DESC LIMIT 100', [':workspace' => $workspaceId]);
    }

    /** @return list<array<string,mixed>> */
    public function syncSessions(int $workspaceId): array
    {
        return $this->queryRows('SELECT BIN_TO_UUID(public_id) AS public_id,status_code,change_count,accepted_count,rejected_count,conflict_count,payload_bytes,opened_at,completed_at FROM synchronization_batches WHERE workspace_id=:workspace ORDER BY opened_at DESC,id DESC LIMIT 100', [':workspace' => $workspaceId]);
    }

    /** @return list<array<string,mixed>> */
    public function conflicts(int $workspaceId): array
    {
        return $this->queryRows('SELECT BIN_TO_UUID(public_id) AS public_id,conflict_type,status_code,version,created_at,assigned_at,resolved_at FROM synchronization_conflicts WHERE workspace_id=:workspace ORDER BY created_at DESC,id DESC LIMIT 100', [':workspace' => $workspaceId]);
    }

    public function claimOperation(string $scopeKind, string $scopeReference, string $operation, string $submissionId, string $requestHash): bool
    {
        if (
            !in_array($scopeKind, ['PLATFORM', 'WORKSPACE', 'DEVICE'], true)
            || preg_match('/\A[a-z0-9._:-]{1,96}\z/', $operation) !== 1
            || $submissionId === '' || strlen($submissionId) > 128
        ) {
            throw new \InvalidArgumentException('P13 operation receipt is invalid.');
        }
        $statement = $this->prepare("INSERT IGNORE INTO p13_operation_receipts (public_id,scope_kind,scope_reference_hash,operation_code,submission_id,request_hash,outcome_code,resource_public_id,created_at) VALUES (:public,:scope,UNHEX(SHA2(:reference,256)),:operation,:submission,UNHEX(:request),'CLAIMED',NULL,UTC_TIMESTAMP(6))");
        $statement->execute([':public' => UuidV7::generate()->toBinary(), ':scope' => $scopeKind, ':reference' => $scopeReference, ':operation' => $operation, ':submission' => $submissionId, ':request' => $requestHash]);
        if ($statement->rowCount() === 1) {
            return true;
        }
        $retry = $this->prepare("UPDATE p13_operation_receipts SET outcome_code='CLAIMED',resource_public_id=NULL WHERE scope_kind=:scope AND scope_reference_hash=UNHEX(SHA2(:reference,256)) AND operation_code=:operation AND submission_id=:submission AND request_hash=UNHEX(:request) AND outcome_code='FAILED'");
        $retry->execute([':scope' => $scopeKind, ':reference' => $scopeReference, ':operation' => $operation, ':submission' => $submissionId, ':request' => $requestHash]);

        return $retry->rowCount() === 1;
    }

    public function completeOperation(string $scopeKind, string $scopeReference, string $operation, string $submissionId, bool $succeeded, ?UuidV7 $resource = null): void
    {
        $statement = $this->prepare("UPDATE p13_operation_receipts SET outcome_code=:outcome,resource_public_id=:resource WHERE scope_kind=:scope AND scope_reference_hash=UNHEX(SHA2(:reference,256)) AND operation_code=:operation AND submission_id=:submission AND outcome_code='CLAIMED'");
        $statement->execute([':outcome' => $succeeded ? 'SUCCEEDED' : 'FAILED', ':resource' => $resource?->toBinary(), ':scope' => $scopeKind, ':reference' => $scopeReference, ':operation' => $operation, ':submission' => $submissionId]);
    }

    public function notificationIntent(int $workspaceId, int $accountId, string $type, UuidV7 $subject, string $route): void
    {
        if (!in_array($type, ['P13_DEVICE_STATUS', 'P13_PACKAGE_STATUS', 'P13_SYNC_STATUS', 'P13_CONFLICT_STATUS'], true)) {
            throw new \InvalidArgumentException('P13 notification intent type is invalid.');
        }
        $payload = $this->canonicalJson->encode(['resource_id' => $subject->toString(), 'route' => $route]);
        $statement = $this->prepare("INSERT INTO competition_notification_intents (public_id,workspace_id,account_id,intent_type,aggregate_kind,aggregate_public_id,deduplication_key,safe_payload,status,attempts,available_at,created_at) VALUES (:public,:workspace,:account,:type,'P13_OPERATION',:aggregate,UNHEX(SHA2(:deduplication,256)),:payload,'PENDING',0,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6)) ON DUPLICATE KEY UPDATE id=id");
        $statement->execute([':public' => UuidV7::generate()->toBinary(), ':workspace' => $workspaceId, ':account' => $accountId, ':type' => $type, ':aggregate' => $subject->toBinary(), ':deduplication' => $route . '|' . $subject->toString(), ':payload' => $payload]);
    }

    public function createPilot(string $code, string $name, string $scope, string $start, string $end, int $actor): UuidV7
    {
        if (
            preg_match('/\A[A-Z0-9][A-Z0-9_-]{2,79}\z/', $code) !== 1 || trim($name) === '' || mb_strlen($name) > 191
            || !in_array($scope, ['VENUE', 'WORKSPACE', 'REGION', 'NATIONAL_COHORT'], true)
        ) {
            throw new \InvalidArgumentException('Pilot definition is invalid.');
        }
        $id = UuidV7::generate();
        $statement = $this->prepare("INSERT INTO pilot_programs (public_id,program_code,name,description,scope_type,planned_start_at,planned_end_at,status_code,version,created_by_account_id,approved_by_account_id,started_at,paused_at,completed_at,cancelled_at,failed_at,safe_reason_code,created_at,updated_at) VALUES (:public,:code,:name,NULL,:scope,:start,:end,'DRAFT',1,:actor,NULL,NULL,NULL,NULL,NULL,NULL,NULL,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");
        $statement->execute([':public' => $id->toBinary(), ':code' => $code, ':name' => trim($name), ':scope' => $scope, ':start' => $start, ':end' => $end, ':actor' => $actor]);

        return $id;
    }

    public function transitionPilot(UuidV7 $pilot, string $target, int $expectedVersion, int $actor, string $reason): void
    {
        $this->transactions->transactional(function () use ($pilot, $target, $expectedVersion, $actor, $reason): void {
            $row = $this->oneForUpdate('SELECT id,status_code,version FROM pilot_programs WHERE public_id=:public FOR UPDATE', [':public' => $pilot->toBinary()]);
            if ($this->integer($row, 'version') !== $expectedVersion) {
                throw new \DomainException('Pilot version is stale.');
            }
            $from = $this->text($row, 'status_code');
            $this->lifecycle->assertPilotTransition($from, $target);
            $this->prepare('UPDATE pilot_programs SET status_code=:target,version=version+1,safe_reason_code=:reason,approved_by_account_id=IF(:approval_target="APPROVED",:actor,approved_by_account_id),started_at=IF(:start_target="ACTIVE" AND started_at IS NULL,UTC_TIMESTAMP(6),started_at),paused_at=IF(:pause_target="PAUSED",UTC_TIMESTAMP(6),paused_at),completed_at=IF(:complete_target="COMPLETED",UTC_TIMESTAMP(6),completed_at),cancelled_at=IF(:cancel_target="CANCELLED",UTC_TIMESTAMP(6),cancelled_at),failed_at=IF(:fail_target="FAILED",UTC_TIMESTAMP(6),failed_at),updated_at=UTC_TIMESTAMP(6) WHERE id=:id')->execute([':target' => $target, ':reason' => $reason, ':approval_target' => $target, ':actor' => $actor, ':start_target' => $target, ':pause_target' => $target, ':complete_target' => $target, ':cancel_target' => $target, ':fail_target' => $target, ':id' => $row['id']]);
            $metadata = $this->canonicalJson->encode(['reason_code' => $reason]);
            $this->prepare('INSERT INTO pilot_events (public_id,pilot_program_id,pilot_site_id,event_code,actor_account_id,previous_status,resulting_status,safe_metadata_json,evidence_sha256,occurred_at) VALUES (:public,:pilot,NULL,:event,:actor,:previous,:resulting,:metadata,UNHEX(SHA2(:evidence,256)),UTC_TIMESTAMP(6))')->execute([':public' => UuidV7::generate()->toBinary(), ':pilot' => $row['id'], ':event' => 'PILOT_' . $target, ':actor' => $actor, ':previous' => $from, ':resulting' => $target, ':metadata' => $metadata, ':evidence' => $pilot->toString() . '|' . $from . '|' . $target . '|' . $expectedVersion]);
        }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }

    public function assignPilotSite(UuidV7 $pilot, UuidV7 $workspace, UuidV7 $venue, UuidV7 $edition, int $ownerAccountId, int $expectedVersion, int $actor): UuidV7
    {
        $database = $this->connections->connection();
        $ownsTransaction = !$database->inTransaction();
        if ($ownsTransaction) {
            $database->beginTransaction();
        }
        try {
            $program = $this->oneForUpdate("SELECT id,status_code,version FROM pilot_programs WHERE public_id=:pilot AND status_code IN ('DRAFT','READINESS_REVIEW','APPROVED') FOR UPDATE", [':pilot' => $pilot->toBinary()]);
            if ($this->integer($program, 'version') !== $expectedVersion) {
                throw new \DomainException('Pilot version is stale.');
            }
            $id = UuidV7::generate();
            $statement = $this->prepare("INSERT INTO pilot_sites (public_id,pilot_program_id,workspace_id,venue_id,edition_id,operational_owner_account_id,status_code,readiness_expires_at,version,created_at,updated_at) SELECT :public,:pilot,w.id,v.id,e.id,:owner,'ASSIGNED',NULL,1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6) FROM workspaces w INNER JOIN competition_venues v ON v.workspace_id=w.id INNER JOIN competition_editions e ON e.workspace_id=w.id INNER JOIN user_accounts a ON a.id=:owner_check AND a.account_status='ACTIVE' WHERE w.public_id=:workspace AND w.status_code='ACTIVE' AND v.public_id=:venue AND e.public_id=:edition");
            $statement->execute([':public' => $id->toBinary(), ':pilot' => $program['id'], ':owner' => $ownerAccountId, ':owner_check' => $ownerAccountId, ':workspace' => $workspace->toBinary(), ':venue' => $venue->toBinary(), ':edition' => $edition->toBinary()]);
            if ($statement->rowCount() !== 1) {
                throw new \DomainException('Pilot site scope is unavailable.');
            }
            $siteInternalId = $this->lastInsertId();
            $programUpdate = $this->prepare("UPDATE pilot_programs SET status_code=IF(status_code='DRAFT','READINESS_REVIEW',status_code),version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=:id AND version=:version");
            $programUpdate->execute([':id' => $program['id'], ':version' => $expectedVersion]);
            if ($programUpdate->rowCount() !== 1) {
                throw new \DomainException('Pilot program changed concurrently.');
            }
            $metadata = $this->canonicalJson->encode(['workspace_id' => $workspace->toString(), 'venue_id' => $venue->toString(), 'edition_id' => $edition->toString()]);
            $this->prepare("INSERT INTO pilot_events (public_id,pilot_program_id,pilot_site_id,event_code,actor_account_id,previous_status,resulting_status,safe_metadata_json,evidence_sha256,occurred_at) VALUES (:public,:pilot,:site,'PILOT_SITE_ASSIGNED',:actor,NULL,'ASSIGNED',:metadata,UNHEX(SHA2(:evidence,256)),UTC_TIMESTAMP(6))")->execute([':public' => UuidV7::generate()->toBinary(), ':pilot' => $program['id'], ':site' => $siteInternalId, ':actor' => $actor, ':metadata' => $metadata, ':evidence' => $id->toString() . '|' . $expectedVersion]);
            if ($ownsTransaction) {
                $database->commit();
            }

            return $id;
        } catch (\Throwable $error) {
            if ($ownsTransaction) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    public function evaluatePilotReadiness(UuidV7 $pilot, UuidV7 $site, int $expectedVersion, string $checkCode, bool $blocking, string $result, string $evidenceReference, string $expiresAt, int $actor): UuidV7
    {
        if (preg_match('/\A[A-Z][A-Z0-9_]{2,95}\z/', $checkCode) !== 1 || !in_array($result, ['PENDING', 'PASS', 'FAIL', 'WAIVED'], true)) {
            throw new \InvalidArgumentException('Pilot readiness result is invalid.');
        }
        if ($result === 'WAIVED' && trim($evidenceReference) === '') {
            throw new \DomainException('A readiness waiver requires evidence.');
        }
        $database = $this->connections->connection();
        $ownsTransaction = !$database->inTransaction();
        if ($ownsTransaction) {
            $database->beginTransaction();
        }
        try {
            $row = $this->oneForUpdate('SELECT s.id,s.workspace_id,s.status_code,s.version,s.pilot_program_id FROM pilot_sites s INNER JOIN pilot_programs p ON p.id=s.pilot_program_id WHERE s.public_id=:site AND p.public_id=:pilot FOR UPDATE', [':site' => $site->toBinary(), ':pilot' => $pilot->toBinary()]);
            if ($this->integer($row, 'version') !== $expectedVersion || in_array($this->text($row, 'status_code'), ['COMPLETED', 'CANCELLED'], true)) {
                throw new \DomainException('Pilot site readiness version or lifecycle is stale.');
            }
            $id = UuidV7::generate();
            $this->prepare('INSERT INTO pilot_site_readiness_checks (public_id,workspace_id,pilot_site_id,check_code,blocking_flag,result_code,evidence_reference_hash,evaluated_by_account_id,evaluated_at,expires_at,created_at) VALUES (:public,:workspace,:site,:check,:blocking,:result,UNHEX(SHA2(:evidence,256)),:actor,UTC_TIMESTAMP(6),:expires,UTC_TIMESTAMP(6))')->execute([':public' => $id->toBinary(), ':workspace' => $row['workspace_id'], ':site' => $row['id'], ':check' => $checkCode, ':blocking' => $blocking ? 1 : 0, ':result' => $result, ':evidence' => $evidenceReference, ':actor' => $actor, ':expires' => $expiresAt]);
            $pending = $this->prepare("SELECT COUNT(*) FROM (SELECT blocking_flag,result_code,ROW_NUMBER() OVER (PARTITION BY check_code ORDER BY id DESC) AS recency FROM pilot_site_readiness_checks WHERE workspace_id=:workspace AND pilot_site_id=:site) latest WHERE recency=1 AND blocking_flag=1 AND result_code NOT IN ('PASS','WAIVED')");
            $pending->execute([':workspace' => $row['workspace_id'], ':site' => $row['id']]);
            $target = $this->scalarInteger($pending->fetchColumn(), 'blocking_readiness_count') === 0 ? 'READY' : 'READINESS_REVIEW';
            $siteUpdate = $this->prepare('UPDATE pilot_sites SET status_code=:target,readiness_expires_at=:expires,version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=:id AND version=:version');
            $siteUpdate->execute([':target' => $target, ':expires' => $expiresAt, ':id' => $row['id'], ':version' => $expectedVersion]);
            if ($siteUpdate->rowCount() !== 1) {
                throw new \DomainException('Pilot site changed concurrently.');
            }
            $metadata = $this->canonicalJson->encode(['check_code' => $checkCode, 'result' => $result, 'blocking' => $blocking]);
            $this->prepare("INSERT INTO pilot_events (public_id,pilot_program_id,pilot_site_id,event_code,actor_account_id,previous_status,resulting_status,safe_metadata_json,evidence_sha256,occurred_at) VALUES (:public,:pilot,:site,'PILOT_READINESS_EVALUATED',:actor,:previous,:resulting,:metadata,UNHEX(SHA2(:evidence,256)),UTC_TIMESTAMP(6))")->execute([':public' => UuidV7::generate()->toBinary(), ':pilot' => $row['pilot_program_id'], ':site' => $row['id'], ':actor' => $actor, ':previous' => $row['status_code'], ':resulting' => $target, ':metadata' => $metadata, ':evidence' => $id->toString() . '|' . hash('sha256', $evidenceReference)]);
            if ($ownsTransaction) {
                $database->commit();
            }

            return $id;
        } catch (\Throwable $error) {
            if ($ownsTransaction) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    public function createPilotIncident(UuidV7 $pilot, UuidV7 $site, int $expectedVersion, string $classification, string $severity, string $statement, int $actor): UuidV7
    {
        $classes = ['SECURITY', 'DATA_INTEGRITY', 'OFFLINE_SYNC', 'DEVICE_FAILURE', 'VENUE_OPERATION', 'ACCESSIBILITY', 'PERFORMANCE', 'NOTIFICATION', 'OTHER_APPROVED'];
        if (!in_array($classification, $classes, true) || !in_array($severity, ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'], true) || trim($statement) === '' || mb_strlen($statement) > 4000) {
            throw new \InvalidArgumentException('Pilot incident is invalid.');
        }
        $database = $this->connections->connection();
        $ownsTransaction = !$database->inTransaction();
        if ($ownsTransaction) {
            $database->beginTransaction();
        }
        try {
            $row = $this->oneForUpdate('SELECT s.id,s.workspace_id,s.status_code,s.version,s.pilot_program_id FROM pilot_sites s INNER JOIN pilot_programs p ON p.id=s.pilot_program_id WHERE s.public_id=:site AND p.public_id=:pilot FOR UPDATE', [':site' => $site->toBinary(), ':pilot' => $pilot->toBinary()]);
            if ($this->integer($row, 'version') !== $expectedVersion || $this->text($row, 'status_code') === 'CANCELLED') {
                throw new \DomainException('Pilot site incident version or lifecycle is stale.');
            }
            $id = UuidV7::generate();
            $encrypted = $this->cryptography->encrypt(trim($statement), $id->toString());
            $ciphertext = $encrypted['nonce'] . $encrypted['ciphertext'];
            $this->prepare("INSERT INTO pilot_incidents (public_id,workspace_id,pilot_program_id,pilot_site_id,classification_code,severity_code,statement_ciphertext,statement_sha256,status_code,version,reported_by_account_id,created_at,updated_at,resolved_at) VALUES (:public,:workspace,:pilot,:site,:classification,:severity,:statement,UNHEX(SHA2(:hash,256)),'OPEN',1,:actor,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL)")->execute([':public' => $id->toBinary(), ':workspace' => $row['workspace_id'], ':pilot' => $row['pilot_program_id'], ':site' => $row['id'], ':classification' => $classification, ':severity' => $severity, ':statement' => $ciphertext, ':hash' => trim($statement), ':actor' => $actor]);
            $metadata = $this->canonicalJson->encode(['classification' => $classification, 'severity' => $severity]);
            $this->prepare("INSERT INTO pilot_events (public_id,pilot_program_id,pilot_site_id,event_code,actor_account_id,previous_status,resulting_status,safe_metadata_json,evidence_sha256,occurred_at) VALUES (:public,:pilot,:site,'PILOT_INCIDENT_RECORDED',:actor,:previous_status,:resulting_status,:metadata,UNHEX(SHA2(:evidence,256)),UTC_TIMESTAMP(6))")->execute([':public' => UuidV7::generate()->toBinary(), ':pilot' => $row['pilot_program_id'], ':site' => $row['id'], ':actor' => $actor, ':previous_status' => $row['status_code'], ':resulting_status' => $row['status_code'], ':metadata' => $metadata, ':evidence' => $id->toString() . '|' . hash('sha256', $statement)]);
            if ($ownsTransaction) {
                $database->commit();
            }

            return $id;
        } catch (\Throwable $error) {
            if ($ownsTransaction) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    public function createRollout(string $code, string $name, string $scope, int $maximumWaveSize, int $actor): UuidV7
    {
        if (
            preg_match('/\A[A-Z0-9][A-Z0-9_-]{2,79}\z/', $code) !== 1 || trim($name) === ''
            || !in_array($scope, ['REGIONAL', 'STATE', 'NATIONAL', 'COHORT'], true) || $maximumWaveSize < 1 || $maximumWaveSize > 500
        ) {
            throw new \InvalidArgumentException('Rollout definition is invalid.');
        }
        $id = UuidV7::generate();
        $this->prepare("INSERT INTO rollout_plans (public_id,rollout_code,name,scope_type,maximum_wave_size,status_code,version,created_by_account_id,created_at,updated_at,activated_at,paused_at,completed_at,cancelled_at) VALUES (:public,:code,:name,:scope,:maximum,'DRAFT',1,:actor,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL,NULL,NULL,NULL)")->execute([':public' => $id->toBinary(), ':code' => $code, ':name' => trim($name), ':scope' => $scope, ':maximum' => $maximumWaveSize, ':actor' => $actor]);

        return $id;
    }

    public function registerDevice(int $workspaceId, UuidV7 $venue, string $code, string $name, string $type, string $platform, string $publicKey, int $actor): UuidV7
    {
        if (
            strlen($publicKey) !== 32 || preg_match('/\A[A-Z0-9][A-Z0-9_-]{2,79}\z/', $code) !== 1
            || !in_array($type, ['BROWSER', 'VENUE_NODE', 'TABLET', 'DESKTOP'], true)
        ) {
            throw new \InvalidArgumentException('Offline device registration is invalid.');
        }
        $id = UuidV7::generate();
        $scopeHash = hash('sha256', $workspaceId . '|' . $venue->toString(), true);
        $statement = $this->prepare("INSERT INTO venue_edge_node_registrations (public_id,workspace_id,venue_id,installation_code,display_name,device_type,platform_code,public_key,public_key_sha256,scope_manifest_sha256,status_code,version,registered_by_account_id,activated_by_account_id,last_seen_at,last_sync_at,created_at,updated_at,activated_at,suspended_at,revoked_at,expired_at) SELECT :public,:workspace,id,:code,:name,:type,:platform,:key,UNHEX(SHA2(:key_hash,256)),:scope_hash,'PENDING',1,:actor,NULL,NULL,NULL,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL,NULL,NULL,NULL FROM competition_venues WHERE workspace_id=:workspace_filter AND public_id=:venue");
        $statement->execute([':public' => $id->toBinary(), ':workspace' => $workspaceId, ':workspace_filter' => $workspaceId, ':code' => $code, ':name' => trim($name), ':type' => $type, ':platform' => substr($platform, 0, 32), ':key' => $publicKey, ':key_hash' => $publicKey, ':scope_hash' => $scopeHash, ':actor' => $actor, ':venue' => $venue->toBinary()]);
        if ($statement->rowCount() !== 1) {
            throw new \DomainException('Venue is not available in the active workspace.');
        }

        return $id;
    }

    public function transitionDevice(int $workspaceId, UuidV7 $device, string $target, int $expectedVersion, int $actor): void
    {
        $allowed = [
            'PENDING' => ['ACTIVE', 'REVOKED'],
            'ACTIVE' => ['SUSPENDED', 'REVOKED', 'EXPIRED'],
            'SUSPENDED' => ['ACTIVE', 'REVOKED', 'EXPIRED'],
        ];
        $database = $this->connections->connection();
        $ownsTransaction = !$database->inTransaction();
        if ($ownsTransaction) {
            $database->beginTransaction();
        }
        try {
            $row = $this->oneForUpdate('SELECT id,status_code,version FROM venue_edge_node_registrations WHERE workspace_id=:workspace AND public_id=:public FOR UPDATE', [':workspace' => $workspaceId, ':public' => $device->toBinary()]);
            $from = $this->text($row, 'status_code');
            if ($this->integer($row, 'version') !== $expectedVersion || !in_array($target, $allowed[$from] ?? [], true)) {
                throw new \DomainException('Offline device transition is not permitted.');
            }
            $this->prepare('UPDATE venue_edge_node_registrations SET status_code=:target,version=version+1,activated_by_account_id=IF(:activation_target="ACTIVE",:actor,activated_by_account_id),activated_at=IF(:activated_at_target="ACTIVE",UTC_TIMESTAMP(6),activated_at),suspended_at=IF(:suspended_at_target="SUSPENDED",UTC_TIMESTAMP(6),suspended_at),revoked_at=IF(:revoked_at_target="REVOKED",UTC_TIMESTAMP(6),revoked_at),expired_at=IF(:expired_at_target="EXPIRED",UTC_TIMESTAMP(6),expired_at),updated_at=UTC_TIMESTAMP(6) WHERE id=:id')->execute([':target' => $target, ':activation_target' => $target, ':actor' => $actor, ':activated_at_target' => $target, ':suspended_at_target' => $target, ':revoked_at_target' => $target, ':expired_at_target' => $target, ':id' => $row['id']]);
            $this->prepare('INSERT INTO offline_device_events (public_id,workspace_id,device_id,event_code,actor_account_id,previous_status,resulting_status,evidence_sha256,occurred_at) VALUES (:public,:workspace,:device,:event,:actor,:previous,:resulting,UNHEX(SHA2(:evidence,256)),UTC_TIMESTAMP(6))')->execute([':public' => UuidV7::generate()->toBinary(), ':workspace' => $workspaceId, ':device' => $row['id'], ':event' => 'DEVICE_' . $target, ':actor' => $actor, ':previous' => $from, ':resulting' => $target, ':evidence' => $device->toString() . '|' . $from . '|' . $target . '|' . $expectedVersion]);
            if ($target === 'REVOKED') {
                $this->prepare("UPDATE offline_assignment_packages SET status_code='REVOKED',revoked_at=UTC_TIMESTAMP(6),version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE workspace_id=:workspace AND venue_edge_node_registration_id=:device AND status_code IN ('READY','DOWNLOADED','ACTIVE')")->execute([':workspace' => $workspaceId, ':device' => $row['id']]);
            }
            if ($ownsTransaction) {
                $database->commit();
            }
        } catch (\Throwable $error) {
            if ($ownsTransaction) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    public function rotateDeviceKey(int $workspaceId, UuidV7 $device, int $expectedVersion, string $keyCode, string $publicKey, string $validUntil, int $actor): UuidV7
    {
        if (strlen($publicKey) !== 32 || preg_match('/\A[A-Z0-9][A-Z0-9_.-]{2,95}\z/', $keyCode) !== 1) {
            throw new \InvalidArgumentException('Offline device rotation key is invalid.');
        }
        $database = $this->connections->connection();
        $ownsTransaction = !$database->inTransaction();
        if ($ownsTransaction) {
            $database->beginTransaction();
        }
        try {
            $row = $this->oneForUpdate("SELECT id,public_key,status_code,version,created_at FROM venue_edge_node_registrations WHERE workspace_id=:workspace AND public_id=:device AND status_code='ACTIVE' FOR UPDATE", [':workspace' => $workspaceId, ':device' => $device->toBinary()]);
            if ($this->integer($row, 'version') !== $expectedVersion) {
                throw new \DomainException('Offline device version is stale.');
            }
            $active = $this->prepare("SELECT id FROM offline_device_keys WHERE workspace_id=:workspace AND device_id=:device AND status_code='ACTIVE' ORDER BY id DESC LIMIT 1 FOR UPDATE");
            $active->execute([':workspace' => $workspaceId, ':device' => $this->integer($row, 'id')]);
            $activeId = $active->fetchColumn();
            $previous = $activeId === false ? null : $this->scalarInteger($activeId, 'active_device_key_id');
            if ($previous === null) {
                $this->prepare("INSERT INTO offline_device_keys (public_id,workspace_id,device_id,key_code,public_key,public_key_sha256,status_code,valid_from,valid_until,rotated_from_key_id,created_at,revoked_at) VALUES (:public,:workspace,:device,:code,:key,UNHEX(SHA2(:hash,256)),'REVOKED',:created,UTC_TIMESTAMP(6),NULL,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))")->execute([':public' => UuidV7::generate()->toBinary(), ':workspace' => $workspaceId, ':device' => $row['id'], ':code' => 'INITIAL-' . $expectedVersion, ':key' => $row['public_key'], ':hash' => $row['public_key'], ':created' => $row['created_at']]);
                $previous = $this->lastInsertId();
            } else {
                $this->prepare("UPDATE offline_device_keys SET status_code='REVOKED',revoked_at=UTC_TIMESTAMP(6) WHERE id=:id AND status_code='ACTIVE'")->execute([':id' => $previous]);
            }
            $id = UuidV7::generate();
            $this->prepare("INSERT INTO offline_device_keys (public_id,workspace_id,device_id,key_code,public_key,public_key_sha256,status_code,valid_from,valid_until,rotated_from_key_id,created_at,revoked_at) VALUES (:public,:workspace,:device,:code,:key,UNHEX(SHA2(:hash,256)),'ACTIVE',UTC_TIMESTAMP(6),:until,:previous,UTC_TIMESTAMP(6),NULL)")->execute([':public' => $id->toBinary(), ':workspace' => $workspaceId, ':device' => $row['id'], ':code' => $keyCode, ':key' => $publicKey, ':hash' => $publicKey, ':until' => $validUntil, ':previous' => $previous]);
            $this->prepare('UPDATE venue_edge_node_registrations SET public_key=:key,public_key_sha256=UNHEX(SHA2(:hash,256)),version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=:id AND version=:version')->execute([':key' => $publicKey, ':hash' => $publicKey, ':id' => $row['id'], ':version' => $expectedVersion]);
            $this->prepare("INSERT INTO offline_device_events (public_id,workspace_id,device_id,event_code,actor_account_id,previous_status,resulting_status,evidence_sha256,occurred_at) VALUES (:public,:workspace,:device,'DEVICE_KEY_ROTATED',:actor,'ACTIVE','ACTIVE',UNHEX(SHA2(:evidence,256)),UTC_TIMESTAMP(6))")->execute([':public' => UuidV7::generate()->toBinary(), ':workspace' => $workspaceId, ':device' => $row['id'], ':actor' => $actor, ':evidence' => $id->toString() . '|' . hash('sha256', $publicKey)]);
            if ($ownsTransaction) {
                $database->commit();
            }

            return $id;
        } catch (\Throwable $error) {
            if ($ownsTransaction) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    public function revokePackage(int $workspaceId, UuidV7 $package, int $expectedVersion, int $actor): void
    {
        $database = $this->connections->connection();
        $ownsTransaction = !$database->inTransaction();
        if ($ownsTransaction) {
            $database->beginTransaction();
        }
        try {
            $row = $this->oneForUpdate("SELECT id,status_code,version FROM offline_assignment_packages WHERE workspace_id=:workspace AND public_id=:public FOR UPDATE", [':workspace' => $workspaceId, ':public' => $package->toBinary()]);
            $from = $this->text($row, 'status_code');
            if ($this->integer($row, 'version') !== $expectedVersion || !in_array($from, ['READY', 'DOWNLOADED', 'ACTIVE'], true)) {
                throw new \DomainException('Offline package cannot be revoked from its current state.');
            }
            $statement = $this->prepare("UPDATE offline_assignment_packages SET status_code='REVOKED',revoked_at=UTC_TIMESTAMP(6),version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=:id AND version=:version");
            $statement->execute([':id' => $row['id'], ':version' => $expectedVersion]);
            if ($statement->rowCount() !== 1) {
                throw new \DomainException('Offline package changed concurrently.');
            }
            $this->appendPackageEvent($workspaceId, $this->integer($row, 'id'), 'PACKAGE_REVOKED', 'ACCOUNT', (string) $actor, $from, 'REVOKED', $package->toString());
            if ($ownsTransaction) {
                $database->commit();
            }
        } catch (\Throwable $error) {
            if ($ownsTransaction) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    public function supersedePackage(int $workspaceId, UuidV7 $package, UuidV7 $replacement, int $expectedVersion, int $actor): void
    {
        $database = $this->connections->connection();
        $ownsTransaction = !$database->inTransaction();
        if ($ownsTransaction) {
            $database->beginTransaction();
        }
        try {
            $current = $this->oneForUpdate("SELECT id,venue_edge_node_registration_id,competition_edition_id,status_code,version FROM offline_assignment_packages WHERE workspace_id=:workspace AND public_id=:package AND status_code IN ('READY','DOWNLOADED','ACTIVE') FOR UPDATE", [':workspace' => $workspaceId, ':package' => $package->toBinary()]);
            $next = $this->oneForUpdate("SELECT id,venue_edge_node_registration_id,competition_edition_id,status_code FROM offline_assignment_packages WHERE workspace_id=:workspace AND public_id=:replacement AND status_code='READY' FOR UPDATE", [':workspace' => $workspaceId, ':replacement' => $replacement->toBinary()]);
            if ($this->integer($current, 'version') !== $expectedVersion || $current['venue_edge_node_registration_id'] !== $next['venue_edge_node_registration_id'] || $current['competition_edition_id'] !== $next['competition_edition_id'] || $current['id'] === $next['id']) {
                throw new \DomainException('Offline package supersession scope or version is stale.');
            }
            $this->prepare("UPDATE offline_assignment_packages SET status_code='SUPERSEDED',superseded_at=UTC_TIMESTAMP(6),superseded_by_package_id=:replacement,version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=:id AND version=:version")->execute([':replacement' => $next['id'], ':id' => $current['id'], ':version' => $expectedVersion]);
            $this->appendPackageEvent($workspaceId, $this->integer($current, 'id'), 'PACKAGE_SUPERSEDED', 'ACCOUNT', (string) $actor, $this->text($current, 'status_code'), 'SUPERSEDED', $package->toString() . '|' . $replacement->toString());
            $this->appendPackageEvent($workspaceId, $this->integer($next, 'id'), 'PACKAGE_REPLACEMENT_ACTIVATED', 'ACCOUNT', (string) $actor, 'READY', 'READY', $replacement->toString() . '|' . $package->toString());
            if ($ownsTransaction) {
                $database->commit();
            }
        } catch (\Throwable $error) {
            if ($ownsTransaction) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    public function createWave(UuidV7 $rollout, string $name, string $start, string $end, int $actor): UuidV7
    {
        $database = $this->connections->connection();
        $ownsTransaction = !$database->inTransaction();
        if ($ownsTransaction) {
            $database->beginTransaction();
        }
        try {
            $plan = $this->oneForUpdate("SELECT id,maximum_wave_size,status_code FROM rollout_plans WHERE public_id=:public AND status_code IN ('DRAFT','READY','ACTIVE','PAUSED') FOR UPDATE", [':public' => $rollout->toBinary()]);
            $numberStatement = $this->prepare('SELECT COALESCE(MAX(wave_number),0)+1 FROM rollout_waves WHERE rollout_plan_id=:plan');
            $numberStatement->execute([':plan' => $plan['id']]);
            $number = $this->scalarInteger($numberStatement->fetchColumn(), 'wave_number');
            $id = UuidV7::generate();
            $this->prepare("INSERT INTO rollout_waves (public_id,rollout_plan_id,wave_number,name,planned_start_at,planned_end_at,status_code,assignment_count,version,created_at,updated_at,activated_at,paused_at,contained_at,completed_at,cancelled_at) VALUES (:public,:plan,:number,:name,:start,:end,'PLANNED',0,1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL,NULL,NULL,NULL,NULL)")->execute([':public' => $id->toBinary(), ':plan' => $plan['id'], ':number' => $number, ':name' => trim($name), ':start' => $start, ':end' => $end]);
            $this->prepare("INSERT INTO rollout_events (public_id,rollout_plan_id,rollout_wave_id,event_code,actor_account_id,previous_status,resulting_status,safe_metadata_json,evidence_sha256,occurred_at) VALUES (:public,:plan,LAST_INSERT_ID(),'WAVE_CREATED',:actor,NULL,'PLANNED',JSON_OBJECT('wave_number',:number),UNHEX(SHA2(:evidence,256)),UTC_TIMESTAMP(6))")->execute([':public' => UuidV7::generate()->toBinary(), ':plan' => $plan['id'], ':actor' => $actor, ':number' => $number, ':evidence' => $id->toString()]);
            if ($ownsTransaction) {
                $database->commit();
            }

            return $id;
        } catch (\Throwable $error) {
            if ($ownsTransaction) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    public function assignConflict(int $workspaceId, UuidV7 $conflict, int $expectedVersion, int $reviewerAccountId, int $actor): UuidV7
    {
        $database = $this->connections->connection();
        $ownsTransaction = !$database->inTransaction();
        if ($ownsTransaction) {
            $database->beginTransaction();
        }
        try {
            $row = $this->oneForUpdate("SELECT id,status_code,version FROM synchronization_conflicts WHERE workspace_id=:workspace AND public_id=:conflict AND status_code='OPEN' FOR UPDATE", [':workspace' => $workspaceId, ':conflict' => $conflict->toBinary()]);
            if ($this->integer($row, 'version') !== $expectedVersion) {
                throw new \DomainException('Offline conflict version is stale.');
            }
            $membership = $this->prepare("SELECT COUNT(*) FROM workspace_memberships WHERE workspace_id=:workspace AND user_account_id=:reviewer AND status_code='ACTIVE'");
            $membership->execute([':workspace' => $workspaceId, ':reviewer' => $reviewerAccountId]);
            if ($this->scalarInteger($membership->fetchColumn(), 'reviewer_membership_count') !== 1) {
                throw new \DomainException('Offline conflict reviewer is outside the workspace.');
            }
            $id = UuidV7::generate();
            $this->prepare("INSERT INTO offline_conflict_assignments (public_id,workspace_id,conflict_id,reviewer_account_id,assigned_by_account_id,status_code,assigned_at,released_at) VALUES (:public,:workspace,:conflict,:reviewer,:actor,'ACTIVE',UTC_TIMESTAMP(6),NULL)")->execute([':public' => $id->toBinary(), ':workspace' => $workspaceId, ':conflict' => $row['id'], ':reviewer' => $reviewerAccountId, ':actor' => $actor]);
            $this->prepare("UPDATE synchronization_conflicts SET status_code='ASSIGNED',assigned_at=UTC_TIMESTAMP(6),version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=:id AND version=:version")->execute([':id' => $row['id'], ':version' => $expectedVersion]);
            if ($ownsTransaction) {
                $database->commit();
            }

            return $id;
        } catch (\Throwable $error) {
            if ($ownsTransaction) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    public function startConflictReview(int $workspaceId, UuidV7 $conflict, int $expectedVersion, int $reviewerAccountId): void
    {
        $database = $this->connections->connection();
        $ownsTransaction = !$database->inTransaction();
        if ($ownsTransaction) {
            $database->beginTransaction();
        }
        try {
            $row = $this->oneForUpdate("SELECT c.id,c.status_code,c.version,a.id assignment_id,a.reviewer_account_id,a.status_code assignment_status FROM synchronization_conflicts c INNER JOIN offline_conflict_assignments a ON a.workspace_id=c.workspace_id AND a.conflict_id=c.id WHERE c.workspace_id=:workspace AND c.public_id=:conflict AND c.status_code='ASSIGNED' AND a.status_code='ACTIVE' FOR UPDATE", [':workspace' => $workspaceId, ':conflict' => $conflict->toBinary()]);
            if ($this->integer($row, 'version') !== $expectedVersion || $this->integer($row, 'reviewer_account_id') !== $reviewerAccountId) {
                throw new \DomainException('Offline conflict review assignment is stale or unauthorized.');
            }
            $this->prepare("UPDATE synchronization_conflicts SET status_code='UNDER_REVIEW',version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=:id AND version=:version")->execute([':id' => $row['id'], ':version' => $expectedVersion]);
            if ($ownsTransaction) {
                $database->commit();
            }
        } catch (\Throwable $error) {
            if ($ownsTransaction) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    public function decideConflict(int $workspaceId, UuidV7 $conflict, string $decision, int $expectedVersion, string $reason, int $actor): void
    {
        $status = match ($decision) {
            'ACCEPT_SERVER' => 'RESOLVED_ACCEPT_SERVER',
            'ACCEPT_CLIENT_PROPOSAL' => 'RESOLVED_ACCEPT_CLIENT_PROPOSAL',
            'MANUAL' => 'RESOLVED_MANUAL',
            'DISMISS' => 'DISMISSED',
            default => throw new \InvalidArgumentException('Conflict decision is invalid.'),
        };
        $this->transactions->transactional(function () use ($workspaceId, $conflict, $decision, $expectedVersion, $reason, $actor, $status): void {
            $row = $this->oneForUpdate("SELECT id,version,status_code FROM synchronization_conflicts WHERE workspace_id=:workspace AND public_id=:public FOR UPDATE", [':workspace' => $workspaceId, ':public' => $conflict->toBinary()]);
            if ($this->integer($row, 'version') !== $expectedVersion || !in_array($row['status_code'], ['OPEN', 'ASSIGNED', 'UNDER_REVIEW'], true)) {
                throw new \DomainException('Conflict decision is stale or unavailable.');
            }
            if ($decision === 'ACCEPT_CLIENT_PROPOSAL') {
                $this->reapplyConflictProposal($workspaceId, $this->integer($row, 'id'), $conflict);
            }
            $emptyHash = hash('sha256', '', true);
            $this->prepare('INSERT INTO offline_conflict_decisions (public_id,workspace_id,conflict_id,decision_code,expected_conflict_version,safe_reason_code,proposal_schema_version,resolution_payload_ciphertext,resolution_payload_sha256,evidence_sha256,decided_by_account_id,decided_at,created_at) VALUES (:public,:workspace,:conflict,:decision,:version,:reason,NULL,NULL,:payload,:evidence,:actor,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))')->execute([':public' => UuidV7::generate()->toBinary(), ':workspace' => $workspaceId, ':conflict' => $row['id'], ':decision' => $decision, ':version' => $expectedVersion, ':reason' => $reason, ':payload' => $emptyHash, ':evidence' => hash('sha256', $conflict->toString() . '|' . $decision . '|' . $expectedVersion, true), ':actor' => $actor]);
            $this->prepare('UPDATE synchronization_conflicts SET status_code=:status,version=version+1,resolved_at=UTC_TIMESTAMP(6),updated_at=UTC_TIMESTAMP(6) WHERE id=:id')->execute([':status' => $status, ':id' => $row['id']]);
        }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }

    /** @param list<array{type:string,id:string,version:int,payload:array<string,mixed>}> $entities */
    public function preparePackage(int $workspaceId, UuidV7 $device, UuidV7 $edition, array $entities, int $actor): UuidV7
    {
        if (count($entities) > 5000) {
            throw new \OverflowException('Offline package contains too many entities.');
        }
        $database = $this->connections->connection();
        $ownsTransaction = !$database->inTransaction();
        if ($ownsTransaction) {
            $database->beginTransaction();
        }
        try {
            $authority = $this->oneForUpdate("SELECT d.id,BIN_TO_UUID(d.public_id) device_public_id,e.id edition_id,BIN_TO_UUID(e.public_id) edition_public_id FROM venue_edge_node_registrations d INNER JOIN competition_editions e ON e.workspace_id=d.workspace_id WHERE d.workspace_id=:workspace AND d.public_id=:device AND e.public_id=:edition AND d.status_code='ACTIVE' FOR UPDATE", [':workspace' => $workspaceId, ':device' => $device->toBinary(), ':edition' => $edition->toBinary()]);
            $packageId = UuidV7::generate();
            $generated = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $expires = $generated->modify('+24 hours');
            $safeEntities = [];
            foreach ($entities as $index => $entity) {
                $entityId = UuidV7::fromString($entity['id']);
                if ($entity['version'] < 1 || preg_match('/\A[A-Z][A-Z0-9_]{1,47}\z/', $entity['type']) !== 1) {
                    throw new \InvalidArgumentException('Offline package entity is invalid.');
                }
                $safeEntities[] = ['type' => $entity['type'], 'id' => $entityId->toString(), 'version' => $entity['version'], 'payload' => $entity['payload'], 'sequence' => $index + 1];
            }
            $manifest = $this->canonicalJson->encode([
                'schema_version' => 1, 'package_id' => $packageId->toString(), 'device_id' => $this->text($authority, 'device_public_id'),
                'edition_id' => $this->text($authority, 'edition_public_id'), 'generated_at' => $generated->format(DATE_ATOM),
                'expires_at' => $expires->format(DATE_ATOM), 'entity_count' => count($safeEntities),
                'allowed_operations' => OfflineOperationPolicy::ALLOWED,
            ]);
            $plaintext = $this->canonicalJson->encode(['manifest' => json_decode($manifest, true, 512, JSON_THROW_ON_ERROR), 'entities' => $safeEntities]);
            $associatedData = $packageId->toString() . '|' . $device->toString();
            $encrypted = $this->cryptography->encrypt($plaintext, $associatedData);
            $signature = $this->cryptography->sign($manifest);
            $artifactHash = hash('sha256', $encrypted['ciphertext'], true);
            $insert = $this->prepare("INSERT INTO offline_assignment_packages (public_id,workspace_id,venue_edge_node_registration_id,competition_edition_id,package_code,schema_version,scope_version,status_code,manifest_canonical_json,manifest_sha256,artifact_sha256,signing_key_code,detached_signature,encryption_key_reference_hash,byte_size,generated_at,expires_at,downloaded_at,activated_at,revoked_at,superseded_at,superseded_by_package_id,version,created_by_account_id,created_at,updated_at) VALUES (:public,:workspace,:device,:edition,:code,1,1,'READY',:manifest,UNHEX(SHA2(:manifest_hash,256)),:artifact_hash,'p13.offline.primary',:signature,UNHEX(SHA2('p13.offline.encryption.primary',256)),:bytes,:generated,:expires,NULL,NULL,NULL,NULL,NULL,1,:actor,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");
            $insert->execute([':public' => $packageId->toBinary(), ':workspace' => $workspaceId, ':device' => $this->integer($authority, 'id'), ':edition' => $this->integer($authority, 'edition_id'), ':code' => 'PKG-' . strtoupper(str_replace('-', '', substr($packageId->toString(), 0, 18))), ':manifest' => $manifest, ':manifest_hash' => $manifest, ':artifact_hash' => $artifactHash, ':signature' => $signature, ':bytes' => strlen($encrypted['ciphertext']), ':generated' => $generated->format('Y-m-d H:i:s.u'), ':expires' => $expires->format('Y-m-d H:i:s.u'), ':actor' => $actor]);
            $internalId = $this->lastInsertId();
            $entityInsert = $this->prepare('INSERT INTO offline_package_entities (public_id,workspace_id,package_id,entity_type,entity_public_id,entity_version,canonical_payload_json,payload_sha256,sort_sequence,created_at) VALUES (:public,:workspace,:package,:type,:entity,:version,:payload,UNHEX(SHA2(:payload_hash,256)),:sequence,UTC_TIMESTAMP(6))');
            foreach ($safeEntities as $entity) {
                $payload = $this->canonicalJson->encode($entity['payload']);
                $entityInsert->execute([':public' => UuidV7::generate()->toBinary(), ':workspace' => $workspaceId, ':package' => $internalId, ':type' => $entity['type'], ':entity' => UuidV7::fromString($entity['id'])->toBinary(), ':version' => $entity['version'], ':payload' => $payload, ':payload_hash' => $payload, ':sequence' => $entity['sequence']]);
            }
            $this->prepare("INSERT INTO offline_package_artifacts (public_id,workspace_id,package_id,storage_reference_hash,encryption_mode,encryption_key_reference_hash,nonce,associated_data_sha256,ciphertext,artifact_sha256,byte_size,created_at) VALUES (:public,:workspace,:package,UNHEX(SHA2(:storage,256)),'XCHACHA20_POLY1305',UNHEX(SHA2('p13.offline.encryption.primary',256)),:nonce,UNHEX(SHA2(:associated,256)),:ciphertext,:artifact_hash,:bytes,UTC_TIMESTAMP(6))")->execute([':public' => UuidV7::generate()->toBinary(), ':workspace' => $workspaceId, ':package' => $internalId, ':storage' => 'database-private:' . $packageId->toString(), ':nonce' => $encrypted['nonce'], ':associated' => $associatedData, ':ciphertext' => $encrypted['ciphertext'], ':artifact_hash' => $artifactHash, ':bytes' => strlen($encrypted['ciphertext'])]);
            $this->appendPackageEvent($workspaceId, $internalId, 'PACKAGE_READY', 'ACCOUNT', (string) $actor, null, 'READY', $manifest);
            if ($ownsTransaction) {
                $database->commit();
            }
        } catch (\Throwable $error) {
            if ($ownsTransaction) {
                $database->rollBack();
            }
            throw $error;
        }

        return $packageId;
    }

    /** @param array<string,mixed> $payload */
    public function validateOfflineOperation(string $operation, array $payload): string
    {
        $this->operations->assertAllowed($operation, $payload);

        return hash('sha256', $this->canonicalJson->encode($payload));
    }

    /** @return array{workspace_id:int,venue_id:int,device_id:int,package_id:int,public_key:string,device_status:string,package_status:string,package_expires:string} */
    public function deviceAuthority(UuidV7 $device, UuidV7 $package): array
    {
        $statement = $this->prepare('SELECT d.workspace_id,d.venue_id,d.id device_id,p.id package_id,d.public_key,d.status_code device_status,p.status_code package_status,p.expires_at package_expires FROM venue_edge_node_registrations d INNER JOIN offline_assignment_packages p ON p.workspace_id=d.workspace_id AND p.venue_edge_node_registration_id=d.id WHERE d.public_id=:device AND p.public_id=:package');
        $statement->execute([':device' => $device->toBinary(), ':package' => $package->toBinary()]);
        $row = $this->row($statement);
        if ($row === null) {
            throw new \DomainException('Offline device authority is unavailable.');
        }

        return [
            'workspace_id' => $this->integer($row, 'workspace_id'),
            'venue_id' => $this->integer($row, 'venue_id'),
            'device_id' => $this->integer($row, 'device_id'),
            'package_id' => $this->integer($row, 'package_id'),
            'public_key' => $this->text($row, 'public_key'),
            'device_status' => $this->text($row, 'device_status'),
            'package_status' => $this->text($row, 'package_status'),
            'package_expires' => $this->text($row, 'package_expires'),
        ];
    }

    /** @param array{workspace_id:int,venue_id:int,device_id:int,package_id:int,public_key:string,device_status:string,package_status:string,package_expires:string} $authority */
    public function claimDeviceNonce(array $authority, string $nonce, string $requestHash, int $timestamp): bool
    {
        if (
            $authority['device_status'] !== 'ACTIVE' || !in_array($authority['package_status'], ['READY', 'DOWNLOADED', 'ACTIVE'], true)
            || new \DateTimeImmutable($authority['package_expires'], new \DateTimeZone('UTC')) <= new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
        ) {
            throw new \DomainException('Offline device or package authority is inactive.');
        }
        $statement = $this->prepare('INSERT IGNORE INTO offline_device_nonces (device_id,nonce_hash,request_hash,request_timestamp,expires_at,created_at) VALUES (:device,UNHEX(SHA2(:nonce,256)),UNHEX(:request),FROM_UNIXTIME(:request_timestamp),DATE_ADD(FROM_UNIXTIME(:expiry_timestamp),INTERVAL 10 MINUTE),UTC_TIMESTAMP(6))');
        $statement->execute([':device' => $authority['device_id'], ':nonce' => $nonce, ':request' => $requestHash, ':request_timestamp' => $timestamp, ':expiry_timestamp' => $timestamp]);
        if ($statement->rowCount() === 1) {
            $this->prepare('UPDATE venue_edge_node_registrations SET last_seen_at=UTC_TIMESTAMP(6),updated_at=UTC_TIMESTAMP(6) WHERE id=:device')->execute([':device' => $authority['device_id']]);
        }

        return $statement->rowCount() === 1;
    }

    /**
     * @param array{workspace_id:int,venue_id:int,device_id:int,package_id:int,public_key:string,device_status:string,package_status:string,package_expires:string} $authority
     * @return array<string,mixed>
     */
    public function packageEnvelope(array $authority, bool $includePayload): array
    {
        return $this->transactions->transactional(function () use ($authority, $includePayload): array {
            $statement = $this->prepare("SELECT BIN_TO_UUID(p.public_id) public_id,p.package_code,p.schema_version,p.scope_version,p.status_code,p.version,p.manifest_canonical_json,p.manifest_sha256,p.artifact_sha256,p.signing_key_code,p.detached_signature,p.byte_size,p.generated_at,p.expires_at,a.nonce,a.ciphertext FROM offline_assignment_packages p INNER JOIN offline_package_artifacts a ON a.package_id=p.id WHERE p.workspace_id=:workspace AND p.id=:package AND p.status_code IN ('READY','DOWNLOADED','ACTIVE') AND p.expires_at>UTC_TIMESTAMP(6) FOR UPDATE");
            $statement->execute([':workspace' => $authority['workspace_id'], ':package' => $authority['package_id']]);
            $row = $this->row($statement);
            if ($row === null) {
                throw new \DomainException('Offline package artifact is unavailable.');
            }
            $result = [
                'package_id' => $this->text($row, 'public_id'), 'package_code' => $this->text($row, 'package_code'),
                'schema_version' => $this->integer($row, 'schema_version'), 'scope_version' => $this->integer($row, 'scope_version'),
                'status' => $this->text($row, 'status_code'), 'manifest' => json_decode($this->text($row, 'manifest_canonical_json'), true, 512, JSON_THROW_ON_ERROR),
                'manifest_sha256' => bin2hex($this->text($row, 'manifest_sha256')), 'artifact_sha256' => bin2hex($this->text($row, 'artifact_sha256')),
                'signing_key_code' => $this->text($row, 'signing_key_code'), 'signature' => base64_encode($this->text($row, 'detached_signature')),
                'verification_public_key' => base64_encode($this->cryptography->publicKey()), 'byte_size' => $this->integer($row, 'byte_size'),
                'generated_at' => $this->text($row, 'generated_at'), 'expires_at' => $this->text($row, 'expires_at'),
            ];
            if ($includePayload) {
                $associatedData = $this->text($row, 'public_id') . '|' . UuidV7::fromBinary($this->devicePublicBinary($authority['device_id']))->toString();
                $result['payload'] = base64_encode($this->cryptography->decrypt($this->text($row, 'ciphertext'), $this->text($row, 'nonce'), $associatedData));
                if ($this->text($row, 'status_code') === 'READY') {
                    $update = $this->prepare("UPDATE offline_assignment_packages SET status_code='DOWNLOADED',downloaded_at=UTC_TIMESTAMP(6),version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=:package AND version=:version AND status_code='READY'");
                    $update->execute([':package' => $authority['package_id'], ':version' => $row['version']]);
                    if ($update->rowCount() !== 1) {
                        throw new \DomainException('Offline package changed concurrently.');
                    }
                    $this->appendPackageEvent($authority['workspace_id'], $authority['package_id'], 'PACKAGE_DOWNLOADED', 'DEVICE', (string) $authority['device_id'], 'READY', 'DOWNLOADED', (string) $this->integer($row, 'version'));
                    $result['status'] = 'DOWNLOADED';
                }
            }

            return $result;
        }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }

    /** @param array{workspace_id:int,venue_id:int,device_id:int,package_id:int,public_key:string,device_status:string,package_status:string,package_expires:string} $authority */
    public function activatePackage(array $authority): void
    {
        $this->transactions->transactional(function () use ($authority): void {
            $row = $this->oneForUpdate("SELECT id,status_code,version FROM offline_assignment_packages WHERE id=:package AND workspace_id=:workspace AND status_code IN ('READY','DOWNLOADED') AND expires_at>UTC_TIMESTAMP(6) FOR UPDATE", [':package' => $authority['package_id'], ':workspace' => $authority['workspace_id']]);
            $statement = $this->prepare("UPDATE offline_assignment_packages SET status_code='ACTIVE',activated_at=COALESCE(activated_at,UTC_TIMESTAMP(6)),version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=:package AND version=:version");
            $statement->execute([':package' => $authority['package_id'], ':version' => $row['version']]);
            if ($statement->rowCount() !== 1) {
                throw new \DomainException('Offline package changed concurrently.');
            }
            $this->appendPackageEvent($authority['workspace_id'], $authority['package_id'], 'PACKAGE_ACTIVATED', 'DEVICE', (string) $authority['device_id'], $this->text($row, 'status_code'), 'ACTIVE', (string) $this->integer($row, 'version'));
        }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }

    /** @param array{workspace_id:int,venue_id:int,device_id:int,package_id:int,public_key:string,device_status:string,package_status:string,package_expires:string} $authority */
    public function openSyncSession(array $authority, UuidV7 $clientSession): UuidV7
    {
        return $this->transactions->transactional(function () use ($authority, $clientSession): UuidV7 {
            $existing = $this->prepare('SELECT public_id FROM synchronization_batches WHERE device_id=:device AND client_session_uuid=:client FOR UPDATE');
            $existing->execute([':device' => $authority['device_id'], ':client' => $clientSession->toBinary()]);
            $binary = $existing->fetchColumn();
            if (is_string($binary)) {
                return UuidV7::fromBinary($binary);
            }
            $id = UuidV7::generate();
            $this->prepare("INSERT INTO synchronization_batches (public_id,workspace_id,venue_id,device_id,offline_assignment_package_id,client_session_uuid,status_code,first_local_sequence,last_local_sequence,change_count,accepted_count,rejected_count,conflict_count,payload_bytes,version,opened_at,completed_at,failed_at,expires_at,safe_reason_code,created_at,updated_at) VALUES (:public,:workspace,:venue,:device,:package,:client,'OPENED',NULL,NULL,0,0,0,0,0,1,UTC_TIMESTAMP(6),NULL,NULL,DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 30 MINUTE),NULL,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))")->execute([':public' => $id->toBinary(), ':workspace' => $authority['workspace_id'], ':venue' => $authority['venue_id'], ':device' => $authority['device_id'], ':package' => $authority['package_id'], ':client' => $clientSession->toBinary()]);

            return $id;
        }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }

    public function assignRolloutWave(UuidV7 $wave, int $expectedVersion, ?UuidV7 $workspace, ?UuidV7 $venue, ?UuidV7 $geography, ?string $cohort, int $actor): UuidV7
    {
        if (($workspace === null || $venue === null) && $geography === null && ($cohort === null || preg_match('/\A[A-Z0-9][A-Z0-9_-]{1,79}\z/', $cohort) !== 1)) {
            throw new \InvalidArgumentException('Rollout assignment scope is invalid.');
        }
        $database = $this->connections->connection();
        $ownsTransaction = !$database->inTransaction();
        if ($ownsTransaction) {
            $database->beginTransaction();
        }
        try {
            $row = $this->oneForUpdate("SELECT w.id,w.rollout_plan_id,w.status_code,w.assignment_count,w.version,p.maximum_wave_size FROM rollout_waves w INNER JOIN rollout_plans p ON p.id=w.rollout_plan_id WHERE w.public_id=:wave AND w.status_code IN ('PLANNED','READINESS_REVIEW') FOR UPDATE", [':wave' => $wave->toBinary()]);
            if ($this->integer($row, 'version') !== $expectedVersion || $this->integer($row, 'assignment_count') >= $this->integer($row, 'maximum_wave_size')) {
                throw new \DomainException('Rollout wave assignment capacity or version is stale.');
            }
            $workspaceInternal = null;
            $venueInternal = null;
            if ($workspace !== null && $venue !== null) {
                $scope = $this->oneForUpdate('SELECT w.id workspace_id,v.id venue_id FROM workspaces w INNER JOIN competition_venues v ON v.workspace_id=w.id WHERE w.public_id=:workspace AND v.public_id=:venue FOR UPDATE', [':workspace' => $workspace->toBinary(), ':venue' => $venue->toBinary()]);
                $workspaceInternal = $this->integer($scope, 'workspace_id');
                $venueInternal = $this->integer($scope, 'venue_id');
            }
            $geographyInternal = null;
            if ($geography !== null) {
                $scope = $this->oneForUpdate('SELECT id FROM geography_administrative_areas WHERE public_id=:geography FOR UPDATE', [':geography' => $geography->toBinary()]);
                $geographyInternal = $this->integer($scope, 'id');
            }
            $id = UuidV7::generate();
            $this->prepare("INSERT INTO rollout_wave_assignments (public_id,rollout_wave_id,workspace_id,venue_id,geography_area_id,cohort_code,status_code,readiness_sha256,created_at,updated_at) VALUES (:public,:wave,:workspace,:venue,:geography,:cohort,'ASSIGNED',NULL,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))")->execute([':public' => $id->toBinary(), ':wave' => $row['id'], ':workspace' => $workspaceInternal, ':venue' => $venueInternal, ':geography' => $geographyInternal, ':cohort' => $cohort]);
            $this->prepare('UPDATE rollout_waves SET assignment_count=assignment_count+1,version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=:id AND version=:version')->execute([':id' => $row['id'], ':version' => $expectedVersion]);
            $metadata = $this->canonicalJson->encode(['assignment_id' => $id->toString()]);
            $this->appendRolloutEvent($this->integer($row, 'rollout_plan_id'), $this->integer($row, 'id'), 'ROLLOUT_ASSIGNMENT_CREATED', $actor, $this->text($row, 'status_code'), $this->text($row, 'status_code'), $metadata, $id->toString());
            if ($ownsTransaction) {
                $database->commit();
            }

            return $id;
        } catch (\Throwable $error) {
            if ($ownsTransaction) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    public function evaluateRolloutWave(UuidV7 $wave, int $expectedVersion, string $readinessEvidence, int $actor): void
    {
        if (preg_match('/\A[a-f0-9]{64}\z/', strtolower($readinessEvidence)) !== 1) {
            throw new \InvalidArgumentException('Rollout readiness evidence is invalid.');
        }
        $database = $this->connections->connection();
        $ownsTransaction = !$database->inTransaction();
        if ($ownsTransaction) {
            $database->beginTransaction();
        }
        try {
            $row = $this->oneForUpdate("SELECT id,rollout_plan_id,status_code,assignment_count,version FROM rollout_waves WHERE public_id=:wave AND status_code IN ('PLANNED','READINESS_REVIEW') FOR UPDATE", [':wave' => $wave->toBinary()]);
            if ($this->integer($row, 'version') !== $expectedVersion || $this->integer($row, 'assignment_count') < 1) {
                throw new \DomainException('Rollout readiness cannot be evaluated.');
            }
            $this->prepare("UPDATE rollout_wave_assignments SET status_code='READY',readiness_sha256=UNHEX(:readiness),updated_at=UTC_TIMESTAMP(6) WHERE rollout_wave_id=:wave AND status_code='ASSIGNED'")->execute([':readiness' => strtolower($readinessEvidence), ':wave' => $row['id']]);
            $this->prepare("UPDATE rollout_waves SET status_code='READINESS_REVIEW',version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=:id AND version=:version")->execute([':id' => $row['id'], ':version' => $expectedVersion]);
            $metadata = $this->canonicalJson->encode(['readiness_sha256' => strtolower($readinessEvidence)]);
            $this->appendRolloutEvent($this->integer($row, 'rollout_plan_id'), $this->integer($row, 'id'), 'ROLLOUT_READINESS_EVALUATED', $actor, $this->text($row, 'status_code'), 'READINESS_REVIEW', $metadata, $wave->toString() . '|' . $readinessEvidence);
            if ($ownsTransaction) {
                $database->commit();
            }
        } catch (\Throwable $error) {
            if ($ownsTransaction) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    public function decideRolloutWave(UuidV7 $wave, int $expectedVersion, string $decision, string $readinessHash, string $healthHash, string $reason, int $actor): UuidV7
    {
        $targets = ['GO' => 'APPROVED', 'NO_GO' => 'PLANNED', 'PAUSE' => 'PAUSED', 'CONTAIN' => 'CONTAINED', 'COMPLETE' => 'COMPLETED'];
        if (!isset($targets[$decision]) || preg_match('/\A[a-f0-9]{64}\z/', strtolower($readinessHash)) !== 1 || preg_match('/\A[a-f0-9]{64}\z/', strtolower($healthHash)) !== 1 || preg_match('/\A[A-Z][A-Z0-9_]{2,63}\z/', $reason) !== 1) {
            throw new \InvalidArgumentException('Rollout decision is invalid.');
        }
        $database = $this->connections->connection();
        $ownsTransaction = !$database->inTransaction();
        if ($ownsTransaction) {
            $database->beginTransaction();
        }
        try {
            $row = $this->oneForUpdate('SELECT id,rollout_plan_id,status_code,version FROM rollout_waves WHERE public_id=:wave FOR UPDATE', [':wave' => $wave->toBinary()]);
            if ($this->integer($row, 'version') !== $expectedVersion) {
                throw new \DomainException('Rollout wave version is stale.');
            }
            $target = $targets[$decision];
            $this->lifecycle->assertWaveTransition($this->text($row, 'status_code'), $target);
            $previousStatement = $this->prepare('SELECT id FROM rollout_decisions WHERE rollout_wave_id=:wave ORDER BY decided_at DESC,id DESC LIMIT 1');
            $previousStatement->execute([':wave' => $this->integer($row, 'id')]);
            $previousValue = $previousStatement->fetchColumn();
            $previous = $previousValue === false ? null : $this->scalarInteger($previousValue, 'previous_rollout_decision_id');
            $id = UuidV7::generate();
            $evidence = hash('sha256', $wave->toString() . '|' . $decision . '|' . $readinessHash . '|' . $healthHash . '|' . $expectedVersion, true);
            $this->prepare('INSERT INTO rollout_decisions (public_id,rollout_wave_id,decision_code,expected_wave_version,readiness_sha256,health_sha256,evidence_sha256,safe_reason_code,decided_by_account_id,supersedes_decision_id,decided_at,created_at) VALUES (:public,:wave,:decision,:version,UNHEX(:readiness),UNHEX(:health),:evidence,:reason,:actor,:previous,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))')->execute([':public' => $id->toBinary(), ':wave' => $this->integer($row, 'id'), ':decision' => $decision, ':version' => $expectedVersion, ':readiness' => strtolower($readinessHash), ':health' => strtolower($healthHash), ':evidence' => $evidence, ':reason' => $reason, ':actor' => $actor, ':previous' => $previous]);
            $this->updateWaveStatus($this->integer($row, 'id'), $target, $expectedVersion);
            $metadata = $this->canonicalJson->encode(['decision' => $decision, 'reason_code' => $reason]);
            $this->appendRolloutEvent($this->integer($row, 'rollout_plan_id'), $this->integer($row, 'id'), 'ROLLOUT_DECISION_' . $decision, $actor, $this->text($row, 'status_code'), $target, $metadata, bin2hex($evidence));
            if ($ownsTransaction) {
                $database->commit();
            }

            return $id;
        } catch (\Throwable $error) {
            if ($ownsTransaction) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    public function transitionRolloutWave(UuidV7 $wave, int $expectedVersion, string $target, string $reason, int $actor): void
    {
        $database = $this->connections->connection();
        $ownsTransaction = !$database->inTransaction();
        if ($ownsTransaction) {
            $database->beginTransaction();
        }
        try {
            $row = $this->oneForUpdate('SELECT id,rollout_plan_id,status_code,version FROM rollout_waves WHERE public_id=:wave FOR UPDATE', [':wave' => $wave->toBinary()]);
            if ($this->integer($row, 'version') !== $expectedVersion) {
                throw new \DomainException('Rollout wave version is stale.');
            }
            $this->lifecycle->assertWaveTransition($this->text($row, 'status_code'), $target);
            $this->updateWaveStatus($this->integer($row, 'id'), $target, $expectedVersion);
            $metadata = $this->canonicalJson->encode(['reason_code' => $reason]);
            $this->appendRolloutEvent($this->integer($row, 'rollout_plan_id'), $this->integer($row, 'id'), 'ROLLOUT_WAVE_' . $target, $actor, $this->text($row, 'status_code'), $target, $metadata, $wave->toString() . '|' . $target . '|' . $expectedVersion);
            if ($ownsTransaction) {
                $database->commit();
            }
        } catch (\Throwable $error) {
            if ($ownsTransaction) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    /**
     * @param array{workspace_id:int,venue_id:int,device_id:int,package_id:int,public_key:string,device_status:string,package_status:string,package_expires:string} $authority
     * @param array<string,mixed> $change
     * @return array<string,mixed>
     */
    public function receiveSyncChange(array $authority, UuidV7 $session, array $change): array
    {
        $changeId = UuidV7::fromString($this->changeString($change, 'change_id'));
        $entityId = UuidV7::fromString($this->changeString($change, 'entity_id'));
        $operation = $this->changeString($change, 'operation');
        $entityType = $this->changeString($change, 'entity_type');
        $sequence = $this->changeInteger($change, 'sequence');
        $expectedVersion = $this->changeInteger($change, 'expected_version');
        $payload = $this->stringKeyedArray($change['payload'] ?? null, 'Offline payload is invalid.');
        $payloadJson = $this->canonicalJson->encode($payload);
        $this->operations->assertAllowed($operation, $payload);
        $suppliedHash = strtolower($this->changeString($change, 'payload_sha256'));
        if (!hash_equals(hash('sha256', $payloadJson), $suppliedHash)) {
            throw new \DomainException('Offline payload hash is invalid.');
        }
        $requestFingerprint = hash('sha256', $this->canonicalJson->encode([
            'device_id' => $authority['device_id'],
            'package_id' => $authority['package_id'],
            'change_id' => $changeId->toString(),
            'sequence' => $sequence,
            'entity_type' => $entityType,
            'entity_id' => $entityId->toString(),
            'operation' => $operation,
            'expected_version' => $expectedVersion,
            'payload_sha256' => $suppliedHash,
        ]));
        return $this->transactions->transactional(function () use ($authority, $session, $changeId, $entityId, $operation, $entityType, $sequence, $expectedVersion, $payload, $payloadJson, $suppliedHash, $requestFingerprint): array {
            $currentAuthority = $this->oneForUpdate('SELECT d.status_code device_status,p.status_code package_status,p.expires_at FROM venue_edge_node_registrations d INNER JOIN offline_assignment_packages p ON p.workspace_id=d.workspace_id AND p.venue_edge_node_registration_id=d.id WHERE d.workspace_id=:workspace AND d.id=:device AND p.id=:package FOR UPDATE', [':workspace' => $authority['workspace_id'], ':device' => $authority['device_id'], ':package' => $authority['package_id']]);
            if ($this->text($currentAuthority, 'device_status') !== 'ACTIVE' || !in_array($this->text($currentAuthority, 'package_status'), ['READY', 'DOWNLOADED', 'ACTIVE'], true) || new \DateTimeImmutable($this->text($currentAuthority, 'expires_at'), new \DateTimeZone('UTC')) <= new \DateTimeImmutable('now', new \DateTimeZone('UTC'))) {
                throw new \DomainException('Offline device or package authority is inactive.');
            }
            $batch = $this->oneForUpdate("SELECT id,status_code,change_count,payload_bytes FROM synchronization_batches WHERE workspace_id=:workspace AND device_id=:device AND public_id=:public AND status_code IN ('OPENED','RECEIVING') AND expires_at>UTC_TIMESTAMP(6) FOR UPDATE", [':workspace' => $authority['workspace_id'], ':device' => $authority['device_id'], ':public' => $session->toBinary()]);
            $replay = $this->prepare('SELECT result_status,safe_reason_code,BIN_TO_UUID(public_id) receipt_public_id,HEX(request_payload_sha256) request_payload_sha256 FROM offline_sync_receipts WHERE device_id=:device AND client_change_uuid=:change FOR UPDATE');
            $replay->execute([':device' => $authority['device_id'], ':change' => $changeId->toBinary()]);
            $prior = $this->row($replay);
            if ($prior !== null) {
                if (!hash_equals(strtolower($this->text($prior, 'request_payload_sha256')), $requestFingerprint)) {
                    throw new \DomainException('Offline change UUID was replayed with a different request.');
                }
                return [
                    'change_id' => $changeId->toString(),
                    'status' => 'DUPLICATE',
                    'original_status' => $this->text($prior, 'result_status'),
                    'reason_code' => $this->nullableText($prior, 'safe_reason_code'),
                    'receipt_id' => $this->text($prior, 'receipt_public_id'),
                ];
            }
            $newBytes = $this->integer($batch, 'payload_bytes') + strlen($payloadJson);
            if ($this->integer($batch, 'change_count') >= 100 || $newBytes > 2_097_152) {
                throw new \OverflowException('Offline synchronization bounds exceeded.');
            }
            $submissionId = UuidV7::generate();
            $encrypted = $this->cryptography->encrypt($payloadJson, $changeId->toString());
            if ($this->countPackageEntity($authority['workspace_id'], $authority['package_id'], $entityType, $entityId) !== 1) {
                $result = OfflineOperationResult::rejected($entityId, 'PACKAGE_SCOPE_INVALID');
            } else {
                $trusted = $this->contextFactory->resolve($authority, $changeId);
                $result = $this->dispatcher->dispatch(new OfflineOperationContext(
                    $trusted['actor'],
                    $trusted['tenant'],
                    $changeId,
                    $entityId,
                    $expectedVersion,
                    $operation,
                    $payload,
                    $submissionId->toString(),
                ));
            }
            $accepted = $result->status === 'ACCEPTED';
            $resultStatus = $result->status;
            $reason = $result->reasonCode;
            $this->prepare('INSERT INTO offline_submission_events (public_id,workspace_id,offline_assignment_package_id,device_id,client_change_uuid,device_sequence,entity_type,entity_public_id,operation_code,expected_record_version,payload_schema_version,payload_ciphertext,payload_sha256,client_occurred_at,received_at,status_code) VALUES (:public,:workspace,:package,:device,:change,:sequence,:entity_type,:entity,:operation,:version,1,:ciphertext,UNHEX(:payload_hash),NULL,UTC_TIMESTAMP(6),:status)')->execute([':public' => $submissionId->toBinary(), ':workspace' => $authority['workspace_id'], ':package' => $authority['package_id'], ':device' => $authority['device_id'], ':change' => $changeId->toBinary(), ':sequence' => $sequence, ':entity_type' => $entityType, ':entity' => $entityId->toBinary(), ':operation' => $operation, ':version' => $expectedVersion, ':ciphertext' => $encrypted['nonce'] . $encrypted['ciphertext'], ':payload_hash' => $suppliedHash, ':status' => $resultStatus]);
            $submissionInternal = $this->lastInsertId();
            $this->prepare('INSERT INTO offline_sync_changes (public_id,workspace_id,sync_session_id,submission_event_id,local_sequence,payload_bytes,status_code,safe_reason_code,received_at,processed_at) VALUES (:public,:workspace,:session,:submission,:sequence,:bytes,:status,:reason,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))')->execute([':public' => UuidV7::generate()->toBinary(), ':workspace' => $authority['workspace_id'], ':session' => $batch['id'], ':submission' => $submissionInternal, ':sequence' => $sequence, ':bytes' => strlen($payloadJson), ':status' => $resultStatus, ':reason' => $reason]);
            $receiptId = UuidV7::generate();
            $this->prepare('INSERT INTO offline_sync_receipts (public_id,workspace_id,venue_id,device_id,sync_session_id,client_change_uuid,local_sequence,entity_type,entity_public_id,operation_code,result_status,server_entity_version,result_public_id,safe_reason_code,request_payload_sha256,result_payload_sha256,received_at,applied_at,created_at) VALUES (:public,:workspace,:venue,:device,:session,:change,:sequence,:entity_type,:entity,:operation,:status,:server_version,:result,:reason,UNHEX(:request_hash),UNHEX(SHA2(:result_hash,256)),UTC_TIMESTAMP(6),IF(:applied_status="ACCEPTED",UTC_TIMESTAMP(6),NULL),UTC_TIMESTAMP(6))')->execute([':public' => $receiptId->toBinary(), ':workspace' => $authority['workspace_id'], ':venue' => $authority['venue_id'], ':device' => $authority['device_id'], ':session' => $batch['id'], ':change' => $changeId->toBinary(), ':sequence' => $sequence, ':entity_type' => $entityType, ':entity' => $entityId->toBinary(), ':operation' => $operation, ':status' => $resultStatus, ':applied_status' => $resultStatus, ':server_version' => $result->version, ':result' => $result->entityId->toBinary(), ':reason' => $reason, ':request_hash' => $requestFingerprint, ':result_hash' => $resultStatus . '|' . $result->state . '|' . ($reason ?? '')]);
            if ($accepted) {
                $this->prepare('INSERT INTO offline_operation_events (public_id,workspace_id,venue_id,device_id,receipt_id,operation_code,entity_public_id,before_version,after_version,evidence_sha256,occurred_at) VALUES (:public,:workspace,:venue,:device,LAST_INSERT_ID(),:operation,:entity,:before,:after,UNHEX(SHA2(:evidence,256)),UTC_TIMESTAMP(6))')->execute([':public' => UuidV7::generate()->toBinary(), ':workspace' => $authority['workspace_id'], ':venue' => $authority['venue_id'], ':device' => $authority['device_id'], ':operation' => $operation, ':entity' => $entityId->toBinary(), ':before' => $expectedVersion, ':after' => $result->version, ':evidence' => $receiptId->toString() . '|' . $requestFingerprint]);
            } else {
                if ($resultStatus === 'CONFLICT') {
                    $conflictId = UuidV7::generate();
                    $conflictType = $reason ?? 'SOURCE_CHANGED';
                    $this->prepare("INSERT INTO synchronization_conflicts (public_id,workspace_id,synchronization_batche_id,offline_submission_event_id,conflict_type,server_version,server_state_sha256,status_code,version,created_at,updated_at,assigned_at,resolved_at) VALUES (:public,:workspace,:session,:submission,:type,:version,UNHEX(SHA2(:evidence,256)),'OPEN',1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL,NULL)")->execute([':public' => $conflictId->toBinary(), ':workspace' => $authority['workspace_id'], ':session' => $batch['id'], ':submission' => $submissionInternal, ':type' => $conflictType, ':version' => $result->version, ':evidence' => $conflictType . '|' . $entityId->toString()]);
                }
            }
            $this->prepare("UPDATE synchronization_batches SET status_code='RECEIVING',first_local_sequence=COALESCE(first_local_sequence,:first_sequence),last_local_sequence=:last_sequence,change_count=change_count+1,accepted_count=accepted_count+:accepted,rejected_count=rejected_count+:rejected,conflict_count=conflict_count+:conflict,payload_bytes=:bytes,version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=:id")->execute([':first_sequence' => $sequence, ':last_sequence' => $sequence, ':accepted' => $accepted ? 1 : 0, ':rejected' => $resultStatus === 'REJECTED' ? 1 : 0, ':conflict' => $resultStatus === 'CONFLICT' ? 1 : 0, ':bytes' => $newBytes, ':id' => $batch['id']]);
            return ['change_id' => $changeId->toString(), 'status' => $resultStatus, 'reason_code' => $reason, 'receipt_id' => $receiptId->toString()];
        }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }

    /**
     * @param array{workspace_id:int,venue_id:int,device_id:int,package_id:int,public_key:string,device_status:string,package_status:string,package_expires:string} $authority
     * @return array<string,int|string>
     */
    public function completeSyncSession(array $authority, UuidV7 $session): array
    {
        return $this->transactions->transactional(function () use ($authority, $session): array {
            $row = $this->oneForUpdate('SELECT id,status_code,accepted_count,rejected_count,conflict_count,first_local_sequence,last_local_sequence FROM synchronization_batches WHERE workspace_id=:workspace AND device_id=:device AND public_id=:public AND status_code IN (\'OPENED\',\'RECEIVING\') FOR UPDATE', [':workspace' => $authority['workspace_id'], ':device' => $authority['device_id'], ':public' => $session->toBinary()]);
            $status = $this->integer($row, 'conflict_count') > 0 ? 'CONFLICTED' : 'COMPLETED';
            $statement = $this->prepare('UPDATE synchronization_batches SET status_code=:status,completed_at=UTC_TIMESTAMP(6),version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=:id AND status_code IN (\'OPENED\',\'RECEIVING\')');
            $statement->execute([':status' => $status, ':id' => $row['id']]);
            if ($statement->rowCount() !== 1) {
                throw new \DomainException('Synchronization session changed concurrently.');
            }
            $evidence = $this->canonicalJson->encode($row);
            $this->prepare("INSERT INTO venue_reconciliation_reports (public_id,workspace_id,synchronization_batche_id,accepted_count,rejected_count,conflict_count,first_sequence,last_sequence,evidence_sha256,outcome_code,completed_at,created_at) VALUES (:public,:workspace,:session,:accepted,:rejected,:conflicts,:first,:last,UNHEX(SHA2(:evidence,256)),:outcome,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))")->execute([':public' => UuidV7::generate()->toBinary(), ':workspace' => $authority['workspace_id'], ':session' => $row['id'], ':accepted' => $row['accepted_count'], ':rejected' => $row['rejected_count'], ':conflicts' => $row['conflict_count'], ':first' => $row['first_local_sequence'], ':last' => $row['last_local_sequence'], ':evidence' => $evidence, ':outcome' => $this->integer($row, 'conflict_count') > 0 ? 'DRIFT' : 'PASS']);
            $this->prepare('UPDATE venue_edge_node_registrations SET last_sync_at=UTC_TIMESTAMP(6),updated_at=UTC_TIMESTAMP(6) WHERE id=:device')->execute([':device' => $authority['device_id']]);

            return [
                'status' => $status,
                'accepted' => $this->integer($row, 'accepted_count'),
                'rejected' => $this->integer($row, 'rejected_count'),
                'conflicts' => $this->integer($row, 'conflict_count'),
            ];
        }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }

    /**
     * @param array{workspace_id:int,venue_id:int,device_id:int,package_id:int,public_key:string,device_status:string,package_status:string,package_expires:string} $authority
     * @return list<array<string,mixed>>
     */
    public function syncReceipts(array $authority, UuidV7 $session): array
    {
        return $this->queryRows('SELECT BIN_TO_UUID(r.public_id) receipt_id,BIN_TO_UUID(r.client_change_uuid) change_id,r.local_sequence,r.operation_code,r.result_status,r.server_entity_version,BIN_TO_UUID(r.result_public_id) result_id,r.safe_reason_code,r.received_at,r.applied_at FROM offline_sync_receipts r INNER JOIN synchronization_batches b ON b.id=r.sync_session_id WHERE r.workspace_id=:workspace AND r.device_id=:device AND b.public_id=:session ORDER BY r.local_sequence ASC,r.id ASC LIMIT 100', [':workspace' => $authority['workspace_id'], ':device' => $authority['device_id'], ':session' => $session->toBinary()]);
    }

    /** @return array{examined:int,changed:int} */
    public function expirePackages(bool $dryRun): array
    {
        $examined = $this->count("SELECT COUNT(*) FROM offline_assignment_packages WHERE status_code IN ('READY','DOWNLOADED','ACTIVE') AND expires_at<=UTC_TIMESTAMP(6)");
        if ($dryRun || $examined === 0) {
            return ['examined' => $examined, 'changed' => 0];
        }
        $statement = $this->prepare("UPDATE offline_assignment_packages SET status_code='EXPIRED',version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE status_code IN ('READY','DOWNLOADED','ACTIVE') AND expires_at<=UTC_TIMESTAMP(6) LIMIT 100");
        $statement->execute();

        return ['examined' => $examined, 'changed' => $statement->rowCount()];
    }

    /** @return array{examined:int,changed:int} */
    public function reconcilePilotReadiness(bool $dryRun): array
    {
        $examined = $this->count("SELECT COUNT(*) FROM pilot_sites WHERE status_code='READY' AND readiness_expires_at<=UTC_TIMESTAMP(6)");
        if ($dryRun || $examined === 0) {
            return ['examined' => $examined, 'changed' => 0];
        }
        $statement = $this->prepare("UPDATE pilot_sites SET status_code='READINESS_REVIEW',version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE status_code='READY' AND readiness_expires_at<=UTC_TIMESTAMP(6) LIMIT 100");
        $statement->execute();

        return ['examined' => $examined, 'changed' => $statement->rowCount()];
    }

    /** @return array{examined:int,changed:int} */
    public function verifyPilotHealth(): array
    {
        $examined = $this->count("SELECT COUNT(*) FROM pilot_sites WHERE status_code NOT IN ('COMPLETED','CANCELLED')");
        $invalid = $this->count("SELECT COUNT(*) FROM pilot_sites s LEFT JOIN pilot_programs p ON p.id=s.pilot_program_id LEFT JOIN workspaces w ON w.id=s.workspace_id LEFT JOIN competition_venues v ON v.workspace_id=s.workspace_id AND v.id=s.venue_id WHERE p.id IS NULL OR w.id IS NULL OR v.id IS NULL");
        if ($invalid !== 0) {
            throw new \RuntimeException('Pilot health verification found invalid authority links.');
        }

        return ['examined' => $examined, 'changed' => 0];
    }

    /** @return array{examined:int,changed:int} */
    public function verifyRolloutPlans(): array
    {
        $examined = $this->count('SELECT COUNT(*) FROM rollout_plans');
        $invalid = $this->count('SELECT COUNT(*) FROM rollout_waves w INNER JOIN rollout_plans p ON p.id=w.rollout_plan_id WHERE w.assignment_count>p.maximum_wave_size OR w.planned_end_at<=w.planned_start_at');
        if ($invalid !== 0) {
            throw new \RuntimeException('Rollout-plan verification found an invalid bounded wave.');
        }

        return ['examined' => $examined, 'changed' => 0];
    }

    /** @return array{examined:int,changed:int} */
    public function processApprovedRolloutWaves(bool $dryRun): array
    {
        $examined = $this->count("SELECT COUNT(*) FROM rollout_waves w WHERE w.status_code='APPROVED' AND w.planned_start_at<=UTC_TIMESTAMP(6) AND EXISTS (SELECT 1 FROM rollout_decisions d WHERE d.rollout_wave_id=w.id AND d.decision_code='GO' AND d.id=(SELECT MAX(d2.id) FROM rollout_decisions d2 WHERE d2.rollout_wave_id=w.id))");
        if ($dryRun || $examined === 0) {
            return ['examined' => $examined, 'changed' => 0];
        }

        return $this->transactions->transactional(function () use ($examined): array {
            $statement = $this->prepare("UPDATE rollout_waves w SET w.status_code='ACTIVE',w.version=w.version+1,w.activated_at=COALESCE(w.activated_at,UTC_TIMESTAMP(6)),w.updated_at=UTC_TIMESTAMP(6) WHERE w.status_code='APPROVED' AND w.planned_start_at<=UTC_TIMESTAMP(6) AND EXISTS (SELECT 1 FROM rollout_decisions d WHERE d.rollout_wave_id=w.id AND d.decision_code='GO' AND d.id=(SELECT MAX(d2.id) FROM rollout_decisions d2 WHERE d2.rollout_wave_id=w.id)) LIMIT 100");
            $statement->execute();

            return ['examined' => $examined, 'changed' => $statement->rowCount()];
        }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
    }

    /** @return array{examined:int,changed:int} */
    public function captureRolloutHealth(bool $dryRun): array
    {
        $examined = $this->count("SELECT COUNT(*) FROM rollout_waves w WHERE w.status_code IN ('ACTIVE','PAUSED','CONTAINED') AND NOT EXISTS (SELECT 1 FROM rollout_health_snapshots h WHERE h.rollout_wave_id=w.id AND h.source_cutoff_at>=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 5 MINUTE))");
        if ($dryRun || $examined === 0) {
            return ['examined' => $examined, 'changed' => 0];
        }
        $statement = $this->prepare("INSERT INTO rollout_health_snapshots (public_id,rollout_plan_id,rollout_wave_id,metrics_json,metrics_sha256,source_cutoff_at,status_code,created_at) SELECT UUID_TO_BIN(UUID()),w.rollout_plan_id,w.id,JSON_OBJECT('assignment_count',w.assignment_count,'active_assignments',(SELECT COUNT(*) FROM rollout_wave_assignments a WHERE a.rollout_wave_id=w.id AND a.status_code='ACTIVE'),'open_conflicts',(SELECT COUNT(*) FROM synchronization_conflicts c WHERE c.status_code IN ('OPEN','ASSIGNED','UNDER_REVIEW'))),UNHEX(SHA2(CONCAT(w.id,'|',w.version,'|',w.assignment_count),256)),UTC_TIMESTAMP(6),'CURRENT',UTC_TIMESTAMP(6) FROM rollout_waves w WHERE w.status_code IN ('ACTIVE','PAUSED','CONTAINED') AND NOT EXISTS (SELECT 1 FROM rollout_health_snapshots h WHERE h.rollout_wave_id=w.id AND h.source_cutoff_at>=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 5 MINUTE)) ORDER BY w.id LIMIT 100");
        $statement->execute();

        return ['examined' => $examined, 'changed' => $statement->rowCount()];
    }

    /** @return array{examined:int,changed:int} */
    public function verifyDevices(): array
    {
        $examined = $this->count('SELECT COUNT(*) FROM venue_edge_node_registrations');
        $invalid = $this->count("SELECT COUNT(*) FROM venue_edge_node_registrations WHERE OCTET_LENGTH(public_key)<>32 OR OCTET_LENGTH(public_key_sha256)<>32 OR OCTET_LENGTH(scope_manifest_sha256)<>32 OR version<1");
        if ($invalid !== 0) {
            throw new \RuntimeException('Offline-device verification found invalid public authority material.');
        }

        return ['examined' => $examined, 'changed' => 0];
    }

    /** @return array{examined:int,changed:int} */
    public function reconcileDeviceKeys(bool $dryRun): array
    {
        $examined = $this->count("SELECT COUNT(*) FROM offline_device_keys WHERE status_code='ACTIVE' AND valid_until<=UTC_TIMESTAMP(6)");
        if ($dryRun || $examined === 0) {
            return ['examined' => $examined, 'changed' => 0];
        }
        $statement = $this->prepare("UPDATE offline_device_keys SET status_code='EXPIRED',revoked_at=UTC_TIMESTAMP(6) WHERE status_code='ACTIVE' AND valid_until<=UTC_TIMESTAMP(6) LIMIT 100");
        $statement->execute();

        return ['examined' => $examined, 'changed' => $statement->rowCount()];
    }

    /** @return array{examined:int,changed:int} */
    public function processPreparedPackages(bool $dryRun): array
    {
        $examined = $this->count("SELECT COUNT(*) FROM offline_assignment_packages p WHERE p.status_code='PREPARING' AND EXISTS (SELECT 1 FROM offline_package_artifacts a WHERE a.package_id=p.id) AND EXISTS (SELECT 1 FROM offline_package_entities e WHERE e.package_id=p.id)");
        if ($dryRun || $examined === 0) {
            return ['examined' => $examined, 'changed' => 0];
        }
        $statement = $this->prepare("UPDATE offline_assignment_packages p SET p.status_code='READY',p.version=p.version+1,p.updated_at=UTC_TIMESTAMP(6) WHERE p.status_code='PREPARING' AND EXISTS (SELECT 1 FROM offline_package_artifacts a WHERE a.package_id=p.id) AND EXISTS (SELECT 1 FROM offline_package_entities e WHERE e.package_id=p.id) LIMIT 100");
        $statement->execute();

        return ['examined' => $examined, 'changed' => $statement->rowCount()];
    }

    /** @return array{examined:int,changed:int} */
    public function verifyPackages(): array
    {
        $examined = $this->count('SELECT COUNT(*) FROM offline_assignment_packages');
        $invalid = $this->count("SELECT COUNT(*) FROM offline_assignment_packages p LEFT JOIN offline_package_artifacts a ON a.package_id=p.id WHERE p.status_code IN ('READY','DOWNLOADED','ACTIVE') AND (a.id IS NULL OR OCTET_LENGTH(p.manifest_sha256)<>32 OR p.artifact_sha256<>a.artifact_sha256 OR p.detached_signature IS NULL)");
        if ($invalid !== 0) {
            throw new \RuntimeException('Offline-package verification found invalid signed artifact evidence.');
        }

        return ['examined' => $examined, 'changed' => 0];
    }

    /** @return array{examined:int,changed:int} */
    public function reconcilePackages(bool $dryRun): array
    {
        $expired = $this->expirePackages($dryRun);
        $failed = $this->count("SELECT COUNT(*) FROM offline_assignment_packages p WHERE p.status_code='PREPARING' AND p.created_at<DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 30 MINUTE) AND NOT EXISTS (SELECT 1 FROM offline_package_artifacts a WHERE a.package_id=p.id)");
        if ($dryRun || $failed === 0) {
            return ['examined' => $expired['examined'] + $failed, 'changed' => $expired['changed']];
        }
        $statement = $this->prepare("UPDATE offline_assignment_packages p SET p.status_code='FAILED',p.version=p.version+1,p.updated_at=UTC_TIMESTAMP(6) WHERE p.status_code='PREPARING' AND p.created_at<DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 30 MINUTE) AND NOT EXISTS (SELECT 1 FROM offline_package_artifacts a WHERE a.package_id=p.id) LIMIT 100");
        $statement->execute();

        return ['examined' => $expired['examined'] + $failed, 'changed' => $expired['changed'] + $statement->rowCount()];
    }

    /** @return array{examined:int,changed:int} */
    public function verifySyncSessions(): array
    {
        $examined = $this->count('SELECT COUNT(*) FROM synchronization_batches');
        $invalid = $this->count('SELECT COUNT(*) FROM synchronization_batches b WHERE b.change_count<>(b.accepted_count+b.rejected_count+b.conflict_count) OR b.change_count>100 OR b.payload_bytes>2097152');
        if ($invalid !== 0) {
            throw new \RuntimeException('Offline synchronization verification found invalid bounded counters.');
        }

        return ['examined' => $examined, 'changed' => 0];
    }

    /** @return array{examined:int,changed:int} */
    public function reconcileSyncSessions(bool $dryRun): array
    {
        $examined = $this->count("SELECT COUNT(*) FROM synchronization_batches WHERE status_code IN ('OPENED','RECEIVING','VALIDATING','APPLYING') AND expires_at<=UTC_TIMESTAMP(6)");
        if ($dryRun || $examined === 0) {
            return ['examined' => $examined, 'changed' => 0];
        }
        $statement = $this->prepare("UPDATE synchronization_batches SET status_code='EXPIRED',safe_reason_code='SESSION_EXPIRED',failed_at=UTC_TIMESTAMP(6),version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE status_code IN ('OPENED','RECEIVING','VALIDATING','APPLYING') AND expires_at<=UTC_TIMESTAMP(6) LIMIT 100");
        $statement->execute();

        return ['examined' => $examined, 'changed' => $statement->rowCount()];
    }

    /** @return array{examined:int,changed:int} */
    public function verifyConflicts(): array
    {
        $examined = $this->count('SELECT COUNT(*) FROM synchronization_conflicts');
        $invalid = $this->count("SELECT COUNT(*) FROM synchronization_conflicts c WHERE (c.status_code IN ('ASSIGNED','UNDER_REVIEW') AND NOT EXISTS (SELECT 1 FROM offline_conflict_assignments a WHERE a.conflict_id=c.id AND a.status_code='ACTIVE')) OR (c.status_code LIKE 'RESOLVED_%' AND c.resolved_at IS NULL)");
        if ($invalid !== 0) {
            throw new \RuntimeException('Offline-conflict verification found invalid lifecycle evidence.');
        }

        return ['examined' => $examined, 'changed' => 0];
    }

    /** @return array{examined:int,changed:int} */
    public function notifyUnresolvedConflicts(bool $dryRun): array
    {
        $examined = $this->count("SELECT COUNT(*) FROM synchronization_conflicts c INNER JOIN offline_conflict_assignments a ON a.conflict_id=c.id AND a.status_code='ACTIVE' WHERE c.status_code IN ('ASSIGNED','UNDER_REVIEW')");
        if ($dryRun || $examined === 0) {
            return ['examined' => $examined, 'changed' => 0];
        }
        $statement = $this->prepare("INSERT IGNORE INTO competition_notification_intents (public_id,workspace_id,account_id,intent_type,aggregate_kind,aggregate_public_id,deduplication_key,safe_payload,status,attempts,available_at,created_at) SELECT UUID_TO_BIN(UUID()),c.workspace_id,a.reviewer_account_id,'P13_CONFLICT_STATUS','OFFLINE_CONFLICT',c.public_id,UNHEX(SHA2(CONCAT('P13_CONFLICT_STATUS|',HEX(c.public_id),'|',c.version),256)),JSON_OBJECT('conflict_id',BIN_TO_UUID(c.public_id),'status',c.status_code),'PENDING',0,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6) FROM synchronization_conflicts c INNER JOIN offline_conflict_assignments a ON a.conflict_id=c.id AND a.status_code='ACTIVE' WHERE c.status_code IN ('ASSIGNED','UNDER_REVIEW') ORDER BY c.id LIMIT 100");
        $statement->execute();

        return ['examined' => $examined, 'changed' => $statement->rowCount()];
    }

    /** @return array{examined:int,changed:int} */
    public function verifyReceipts(): array
    {
        $examined = $this->count('SELECT COUNT(*) FROM offline_sync_receipts');
        $invalid = $this->count('SELECT COUNT(*) FROM offline_sync_receipts r LEFT JOIN offline_sync_changes c ON c.sync_session_id=r.sync_session_id AND c.local_sequence=r.local_sequence WHERE c.id IS NULL OR c.status_code<>r.result_status OR OCTET_LENGTH(r.request_payload_sha256)<>32 OR OCTET_LENGTH(r.result_payload_sha256)<>32');
        if ($invalid !== 0) {
            throw new \RuntimeException('Offline-receipt verification found missing or divergent immutable evidence.');
        }

        return ['examined' => $examined, 'changed' => 0];
    }

    /** @return array{examined:int,changed:int} */
    public function reconcileReceipts(bool $dryRun): array
    {
        $examined = $this->count('SELECT COUNT(*) FROM offline_sync_changes c INNER JOIN offline_sync_receipts r ON r.sync_session_id=c.sync_session_id AND r.local_sequence=c.local_sequence WHERE c.status_code<>r.result_status');
        if ($dryRun || $examined === 0) {
            return ['examined' => $examined, 'changed' => 0];
        }
        $statement = $this->prepare('UPDATE offline_sync_changes c INNER JOIN offline_sync_receipts r ON r.sync_session_id=c.sync_session_id AND r.local_sequence=c.local_sequence SET c.status_code=r.result_status,c.safe_reason_code=r.safe_reason_code,c.processed_at=COALESCE(c.processed_at,UTC_TIMESTAMP(6)) WHERE c.status_code<>r.result_status LIMIT 100');
        $statement->execute();

        return ['examined' => $examined, 'changed' => $statement->rowCount()];
    }

    /** @return array{examined:int,changed:int} */
    public function verifyRuntime(): array
    {
        $this->verifyFoundation();

        return ['examined' => count(self::TABLES), 'changed' => 0];
    }

    /**
     * @param array<string,int|string|null> $parameters
     * @return list<array<string,mixed>>
     */
    private function queryRows(string $sql, array $parameters = []): array
    {
        $statement = $this->prepare($sql);
        $statement->execute($parameters);

        return $this->rows($statement);
    }

    /**
     * @param array<string,int|string|null> $parameters
     * @return array<string,mixed>
     */
    private function oneForUpdate(string $sql, array $parameters): array
    {
        $statement = $this->prepare($sql);
        $statement->execute($parameters);
        $row = $this->row($statement);
        if ($row === null) {
            throw new \DomainException('Governed P13 resource was not found.');
        }

        return $row;
    }

    private function appendPackageEvent(int $workspaceId, int $packageId, string $event, string $actorKind, string $actor, ?string $previous, string $resulting, string $evidence): void
    {
        $this->prepare('INSERT INTO offline_package_events (public_id,workspace_id,package_id,event_code,actor_kind,actor_reference_hash,previous_status,resulting_status,evidence_sha256,occurred_at) VALUES (:public,:workspace,:package,:event,:kind,UNHEX(SHA2(:actor,256)),:previous,:resulting,UNHEX(SHA2(:evidence,256)),UTC_TIMESTAMP(6))')->execute([':public' => UuidV7::generate()->toBinary(), ':workspace' => $workspaceId, ':package' => $packageId, ':event' => $event, ':kind' => $actorKind, ':actor' => $actor, ':previous' => $previous, ':resulting' => $resulting, ':evidence' => $evidence]);
    }

    private function updateWaveStatus(int $waveId, string $target, int $expectedVersion): void
    {
        $statement = $this->prepare('UPDATE rollout_waves SET status_code=:target,version=version+1,activated_at=IF(:active_target="ACTIVE" AND activated_at IS NULL,UTC_TIMESTAMP(6),activated_at),paused_at=IF(:pause_target="PAUSED",UTC_TIMESTAMP(6),paused_at),contained_at=IF(:contain_target="CONTAINED",UTC_TIMESTAMP(6),contained_at),completed_at=IF(:complete_target="COMPLETED",UTC_TIMESTAMP(6),completed_at),cancelled_at=IF(:cancel_target="CANCELLED",UTC_TIMESTAMP(6),cancelled_at),updated_at=UTC_TIMESTAMP(6) WHERE id=:id AND version=:version');
        $statement->execute([':target' => $target, ':active_target' => $target, ':pause_target' => $target, ':contain_target' => $target, ':complete_target' => $target, ':cancel_target' => $target, ':id' => $waveId, ':version' => $expectedVersion]);
        if ($statement->rowCount() !== 1) {
            throw new \DomainException('Rollout wave changed concurrently.');
        }
    }

    private function appendRolloutEvent(int $planId, int $waveId, string $event, int $actor, ?string $previous, string $resulting, string $metadata, string $evidence): void
    {
        $this->prepare('INSERT INTO rollout_events (public_id,rollout_plan_id,rollout_wave_id,event_code,actor_account_id,previous_status,resulting_status,safe_metadata_json,evidence_sha256,occurred_at) VALUES (:public,:plan,:wave,:event,:actor,:previous,:resulting,:metadata,UNHEX(SHA2(:evidence,256)),UTC_TIMESTAMP(6))')->execute([':public' => UuidV7::generate()->toBinary(), ':plan' => $planId, ':wave' => $waveId, ':event' => $event, ':actor' => $actor, ':previous' => $previous, ':resulting' => $resulting, ':metadata' => $metadata, ':evidence' => $evidence]);
    }

    private function countPackageEntity(int $workspaceId, int $packageId, string $entityType, UuidV7 $entityId): int
    {
        $statement = $this->prepare('SELECT COUNT(*) FROM offline_package_entities WHERE workspace_id=:workspace AND package_id=:package AND entity_type=:type AND entity_public_id=:entity');
        $statement->execute([':workspace' => $workspaceId, ':package' => $packageId, ':type' => $entityType, ':entity' => $entityId->toBinary()]);

        return $this->scalarInteger($statement->fetchColumn(), 'package_entity_count');
    }

    private function reapplyConflictProposal(int $workspaceId, int $conflictId, UuidV7 $conflictPublicId): void
    {
        $row = $this->oneForUpdate(
            'SELECT s.client_change_uuid,s.entity_public_id,s.operation_code,s.expected_record_version,s.payload_ciphertext,'
            . 'd.workspace_id,d.venue_id,d.id device_id,d.public_key,d.status_code device_status,'
            . 'p.id package_id,p.status_code package_status,p.expires_at package_expires '
            . 'FROM synchronization_conflicts c '
            . 'INNER JOIN offline_submission_events s ON s.id=c.offline_submission_event_id '
            . 'INNER JOIN venue_edge_node_registrations d ON d.workspace_id=s.workspace_id AND d.id=s.device_id '
            . 'INNER JOIN offline_assignment_packages p ON p.workspace_id=s.workspace_id AND p.id=s.offline_assignment_package_id '
            . 'WHERE c.workspace_id=:workspace AND c.id=:conflict FOR UPDATE',
            [':workspace' => $workspaceId, ':conflict' => $conflictId],
        );
        $changeId = UuidV7::fromBinary($this->text($row, 'client_change_uuid'));
        $encrypted = $this->text($row, 'payload_ciphertext');
        if (strlen($encrypted) <= 24) {
            throw new \DomainException('Conflict proposal payload is unavailable.');
        }
        $payloadJson = $this->cryptography->decrypt(substr($encrypted, 24), substr($encrypted, 0, 24), $changeId->toString());
        $payload = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload)) {
            throw new \DomainException('Conflict proposal payload is invalid.');
        }
        /** @var array{workspace_id:int,venue_id:int,device_id:int,package_id:int,public_key:string,device_status:string,package_status:string,package_expires:string} $authority */
        $authority = [
            'workspace_id' => $this->integer($row, 'workspace_id'),
            'venue_id' => $this->integer($row, 'venue_id'),
            'device_id' => $this->integer($row, 'device_id'),
            'package_id' => $this->integer($row, 'package_id'),
            'public_key' => $this->text($row, 'public_key'),
            'device_status' => $this->text($row, 'device_status'),
            'package_status' => $this->text($row, 'package_status'),
            'package_expires' => $this->text($row, 'package_expires'),
        ];
        $trusted = $this->contextFactory->resolve($authority, $changeId);
        $result = $this->dispatcher->dispatch(new OfflineOperationContext(
            $trusted['actor'],
            $trusted['tenant'],
            $changeId,
            UuidV7::fromBinary($this->text($row, 'entity_public_id')),
            $this->integer($row, 'expected_record_version'),
            $this->text($row, 'operation_code'),
            $this->stringKeyedArray($payload, 'Conflict proposal payload is invalid.'),
            $conflictPublicId->toString(),
        ));
        if ($result->status !== 'ACCEPTED') {
            throw new \DomainException('Client proposal remains in authoritative conflict.');
        }
    }

    private function devicePublicBinary(int $deviceId): string
    {
        $statement = $this->prepare('SELECT public_id FROM venue_edge_node_registrations WHERE id=:id');
        $statement->execute([':id' => $deviceId]);
        $value = $statement->fetchColumn();
        if (!is_string($value)) {
            throw new \DomainException('Offline device is unavailable.');
        }

        return $value;
    }

    /** @param array<string,mixed> $change */
    private function changeString(array $change, string $name): string
    {
        $value = $change[$name] ?? null;
        if (!is_string($value) || trim($value) === '' || strlen($value) > 191) {
            throw new \InvalidArgumentException('Offline change field is invalid.');
        }

        return trim($value);
    }

    /** @param array<string,mixed> $change */
    private function changeInteger(array $change, string $name): int
    {
        $value = $change[$name] ?? null;
        if ((!is_int($value) && !is_string($value)) || preg_match('/\A[1-9][0-9]{0,18}\z/', (string) $value) !== 1) {
            throw new \InvalidArgumentException('Offline change sequence or version is invalid.');
        }

        return (int) $value;
    }

    private function count(string $sql): int
    {
        $statement = $this->connections->connection()->query($sql);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('P13 count query could not be executed.');
        }
        $value = $statement->fetchColumn();

        return $this->scalarInteger($value, 'count');
    }

    /** @return list<string> */
    private function column(string $sql): array
    {
        $statement = $this->connections->connection()->query($sql);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('P13 column query could not be executed.');
        }
        $values = $statement->fetchAll(PDO::FETCH_COLUMN);

        return array_values(array_filter($values, 'is_string'));
    }

    private function prepare(string $sql): PDOStatement
    {
        $statement = $this->connections->connection()->prepare($sql);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('P13 database statement could not be prepared.');
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
            throw new \RuntimeException('P13 database row is invalid.');
        }

        return $this->normalizeRow($row);
    }

    /** @return list<array<string,mixed>> */
    private function rows(PDOStatement $statement): array
    {
        $rows = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            if (!is_array($row)) {
                throw new \RuntimeException('P13 database row is invalid.');
            }
            $rows[] = $this->normalizeRow($row);
        }

        return $rows;
    }

    /**
     * @param array<array-key,mixed> $row
     * @return array<string,mixed>
     */
    private function normalizeRow(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            if (!is_string($key)) {
                throw new \RuntimeException('P13 database column is invalid.');
            }
            $normalized[$key] = $value;
        }

        return $normalized;
    }

    /** @param array<string,mixed> $row */
    private function integer(array $row, string $key): int
    {
        return $this->scalarInteger($row[$key] ?? null, $key);
    }

    private function scalarInteger(mixed $value, string $key): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value) || preg_match('/\A-?[0-9]+\z/', $value) !== 1) {
            throw new \RuntimeException('P13 integer value is invalid: ' . $key);
        }

        return (int) $value;
    }

    /** @param array<string,mixed> $row */
    private function text(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value)) {
            throw new \RuntimeException('P13 text value is invalid: ' . $key);
        }

        return $value;
    }

    /** @param array<string,mixed> $row */
    private function nullableText(array $row, string $key): ?string
    {
        if (($row[$key] ?? null) === null) {
            return null;
        }

        return $this->text($row, $key);
    }

    private function lastInsertId(): int
    {
        return $this->scalarInteger($this->connections->connection()->lastInsertId(), 'last_insert_id');
    }

    /** @return array<string,mixed> */
    private function stringKeyedArray(mixed $value, string $message): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException($message);
        }
        $normalized = [];
        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException($message);
            }
            $normalized[$key] = $item;
        }

        return $normalized;
    }
}
