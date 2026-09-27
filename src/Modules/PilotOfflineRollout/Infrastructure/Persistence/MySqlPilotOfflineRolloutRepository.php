<?php

declare(strict_types=1);

namespace Qmdb\Modules\PilotOfflineRollout\Infrastructure\Persistence;

use PDO;
use PDOStatement;
use Qmdb\Modules\PilotOfflineRollout\Domain\CanonicalJson;
use Qmdb\Modules\PilotOfflineRollout\Domain\OfflineOperationPolicy;
use Qmdb\Modules\PilotOfflineRollout\Domain\OfflinePackageCryptography;
use Qmdb\Modules\PilotOfflineRollout\Domain\PilotRolloutLifecycle;
use Qmdb\Modules\SecurityAuthorization\Domain\PilotOfflineRolloutP13AuthorizationCatalog;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
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
    public function devices(int $workspaceId): array
    {
        return $this->queryRows('SELECT BIN_TO_UUID(public_id) AS public_id,installation_code,display_name,device_type,platform_code,status_code,version,last_seen_at,last_sync_at FROM venue_edge_node_registrations WHERE workspace_id=:workspace ORDER BY created_at DESC,id DESC LIMIT 100', [':workspace' => $workspaceId]);
    }

    /** @return list<array<string,mixed>> */
    public function packages(int $workspaceId): array
    {
        return $this->queryRows('SELECT BIN_TO_UUID(public_id) AS public_id,package_code,status_code,schema_version,scope_version,byte_size,generated_at,expires_at FROM offline_assignment_packages WHERE workspace_id=:workspace ORDER BY created_at DESC,id DESC LIMIT 100', [':workspace' => $workspaceId]);
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

        return $statement->rowCount() === 1;
    }

    public function completeOperation(string $scopeKind, string $scopeReference, string $operation, string $submissionId, bool $succeeded, ?UuidV7 $resource = null): void
    {
        $statement = $this->prepare("UPDATE p13_operation_receipts SET outcome_code=:outcome,resource_public_id=:resource WHERE scope_kind=:scope AND scope_reference_hash=UNHEX(SHA2(:reference,256)) AND operation_code=:operation AND submission_id=:submission AND outcome_code='CLAIMED'");
        $statement->execute([':outcome' => $succeeded ? 'SUCCEEDED' : 'FAILED', ':resource' => $resource?->toBinary(), ':scope' => $scopeKind, ':reference' => $scopeReference, ':operation' => $operation, ':submission' => $submissionId]);
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
        $database = $this->connections->connection();
        $database->beginTransaction();
        try {
            $row = $this->oneForUpdate('SELECT id,status_code,version FROM pilot_programs WHERE public_id=:public FOR UPDATE', [':public' => $pilot->toBinary()]);
            if ($this->integer($row, 'version') !== $expectedVersion) {
                throw new \DomainException('Pilot version is stale.');
            }
            $from = $this->text($row, 'status_code');
            $this->lifecycle->assertPilotTransition($from, $target);
            $this->prepare('UPDATE pilot_programs SET status_code=:target,version=version+1,safe_reason_code=:reason,approved_by_account_id=IF(:target="APPROVED",:actor,approved_by_account_id),started_at=IF(:target="ACTIVE" AND started_at IS NULL,UTC_TIMESTAMP(6),started_at),paused_at=IF(:target="PAUSED",UTC_TIMESTAMP(6),paused_at),completed_at=IF(:target="COMPLETED",UTC_TIMESTAMP(6),completed_at),cancelled_at=IF(:target="CANCELLED",UTC_TIMESTAMP(6),cancelled_at),failed_at=IF(:target="FAILED",UTC_TIMESTAMP(6),failed_at),updated_at=UTC_TIMESTAMP(6) WHERE id=:id')->execute([':target' => $target, ':reason' => $reason, ':actor' => $actor, ':id' => $row['id']]);
            $metadata = $this->canonicalJson->encode(['reason_code' => $reason]);
            $this->prepare('INSERT INTO pilot_events (public_id,pilot_program_id,pilot_site_id,event_code,actor_account_id,previous_status,resulting_status,safe_metadata_json,evidence_sha256,occurred_at) VALUES (:public,:pilot,NULL,:event,:actor,:previous,:resulting,:metadata,UNHEX(SHA2(:evidence,256)),UTC_TIMESTAMP(6))')->execute([':public' => UuidV7::generate()->toBinary(), ':pilot' => $row['id'], ':event' => 'PILOT_' . $target, ':actor' => $actor, ':previous' => $from, ':resulting' => $target, ':metadata' => $metadata, ':evidence' => $pilot->toString() . '|' . $from . '|' . $target . '|' . $expectedVersion]);
            $database->commit();
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
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
        $statement = $this->prepare("INSERT INTO venue_edge_node_registrations (public_id,workspace_id,venue_id,installation_code,display_name,device_type,platform_code,public_key,public_key_sha256,scope_manifest_sha256,status_code,version,registered_by_account_id,activated_by_account_id,last_seen_at,last_sync_at,created_at,updated_at,activated_at,suspended_at,revoked_at,expired_at) SELECT :public,:workspace,id,:code,:name,:type,:platform,:key,UNHEX(SHA2(:key_hash,256)),:scope_hash,'PENDING',1,:actor,NULL,NULL,NULL,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL,NULL,NULL,NULL FROM competition_venues WHERE workspace_id=:workspace AND public_id=:venue");
        $statement->execute([':public' => $id->toBinary(), ':workspace' => $workspaceId, ':code' => $code, ':name' => trim($name), ':type' => $type, ':platform' => substr($platform, 0, 32), ':key' => $publicKey, ':key_hash' => $publicKey, ':scope_hash' => $scopeHash, ':actor' => $actor, ':venue' => $venue->toBinary()]);
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
        $database->beginTransaction();
        try {
            $row = $this->oneForUpdate('SELECT id,status_code,version FROM venue_edge_node_registrations WHERE workspace_id=:workspace AND public_id=:public FOR UPDATE', [':workspace' => $workspaceId, ':public' => $device->toBinary()]);
            $from = $this->text($row, 'status_code');
            if ($this->integer($row, 'version') !== $expectedVersion || !in_array($target, $allowed[$from] ?? [], true)) {
                throw new \DomainException('Offline device transition is not permitted.');
            }
            $this->prepare('UPDATE venue_edge_node_registrations SET status_code=:target,version=version+1,activated_by_account_id=IF(:target="ACTIVE",:actor,activated_by_account_id),activated_at=IF(:target="ACTIVE",UTC_TIMESTAMP(6),activated_at),suspended_at=IF(:target="SUSPENDED",UTC_TIMESTAMP(6),suspended_at),revoked_at=IF(:target="REVOKED",UTC_TIMESTAMP(6),revoked_at),expired_at=IF(:target="EXPIRED",UTC_TIMESTAMP(6),expired_at),updated_at=UTC_TIMESTAMP(6) WHERE id=:id')->execute([':target' => $target, ':actor' => $actor, ':id' => $row['id']]);
            $this->prepare('INSERT INTO offline_device_events (public_id,workspace_id,device_id,event_code,actor_account_id,previous_status,resulting_status,evidence_sha256,occurred_at) VALUES (:public,:workspace,:device,:event,:actor,:previous,:resulting,UNHEX(SHA2(:evidence,256)),UTC_TIMESTAMP(6))')->execute([':public' => UuidV7::generate()->toBinary(), ':workspace' => $workspaceId, ':device' => $row['id'], ':event' => 'DEVICE_' . $target, ':actor' => $actor, ':previous' => $from, ':resulting' => $target, ':evidence' => $device->toString() . '|' . $from . '|' . $target . '|' . $expectedVersion]);
            if ($target === 'REVOKED') {
                $this->prepare("UPDATE offline_assignment_packages SET status_code='REVOKED',revoked_at=UTC_TIMESTAMP(6),version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE workspace_id=:workspace AND venue_edge_node_registration_id=:device AND status_code IN ('READY','DOWNLOADED','ACTIVE')")->execute([':workspace' => $workspaceId, ':device' => $row['id']]);
            }
            $database->commit();
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    public function revokePackage(int $workspaceId, UuidV7 $package, int $actor): void
    {
        $database = $this->connections->connection();
        $database->beginTransaction();
        try {
            $row = $this->oneForUpdate("SELECT id,status_code FROM offline_assignment_packages WHERE workspace_id=:workspace AND public_id=:public FOR UPDATE", [':workspace' => $workspaceId, ':public' => $package->toBinary()]);
            $from = $this->text($row, 'status_code');
            if (!in_array($from, ['READY', 'DOWNLOADED', 'ACTIVE'], true)) {
                throw new \DomainException('Offline package cannot be revoked from its current state.');
            }
            $this->prepare("UPDATE offline_assignment_packages SET status_code='REVOKED',revoked_at=UTC_TIMESTAMP(6),version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=:id")->execute([':id' => $row['id']]);
            $this->appendPackageEvent($workspaceId, $this->integer($row, 'id'), 'PACKAGE_REVOKED', 'ACCOUNT', (string) $actor, $from, 'REVOKED', $package->toString());
            $database->commit();
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    public function createWave(UuidV7 $rollout, string $name, string $start, string $end, int $actor): UuidV7
    {
        $database = $this->connections->connection();
        $database->beginTransaction();
        try {
            $plan = $this->oneForUpdate("SELECT id,maximum_wave_size,status_code FROM rollout_plans WHERE public_id=:public AND status_code IN ('DRAFT','READY','ACTIVE','PAUSED') FOR UPDATE", [':public' => $rollout->toBinary()]);
            $numberStatement = $this->prepare('SELECT COALESCE(MAX(wave_number),0)+1 FROM rollout_waves WHERE rollout_plan_id=:plan');
            $numberStatement->execute([':plan' => $plan['id']]);
            $number = $this->scalarInteger($numberStatement->fetchColumn(), 'wave_number');
            $id = UuidV7::generate();
            $this->prepare("INSERT INTO rollout_waves (public_id,rollout_plan_id,wave_number,name,planned_start_at,planned_end_at,status_code,assignment_count,version,created_at,updated_at,activated_at,paused_at,contained_at,completed_at,cancelled_at) VALUES (:public,:plan,:number,:name,:start,:end,'PLANNED',0,1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL,NULL,NULL,NULL,NULL)")->execute([':public' => $id->toBinary(), ':plan' => $plan['id'], ':number' => $number, ':name' => trim($name), ':start' => $start, ':end' => $end]);
            $this->prepare("INSERT INTO rollout_events (public_id,rollout_plan_id,rollout_wave_id,event_code,actor_account_id,previous_status,resulting_status,safe_metadata_json,evidence_sha256,occurred_at) VALUES (:public,:plan,LAST_INSERT_ID(),'WAVE_CREATED',:actor,NULL,'PLANNED',JSON_OBJECT('wave_number',:number),UNHEX(SHA2(:evidence,256)),UTC_TIMESTAMP(6))")->execute([':public' => UuidV7::generate()->toBinary(), ':plan' => $plan['id'], ':actor' => $actor, ':number' => $number, ':evidence' => $id->toString()]);
            $database->commit();

            return $id;
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
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
        $database = $this->connections->connection();
        $database->beginTransaction();
        try {
            $row = $this->oneForUpdate("SELECT id,version,status_code FROM synchronization_conflicts WHERE workspace_id=:workspace AND public_id=:public FOR UPDATE", [':workspace' => $workspaceId, ':public' => $conflict->toBinary()]);
            if ($this->integer($row, 'version') !== $expectedVersion || !in_array($row['status_code'], ['OPEN', 'ASSIGNED', 'UNDER_REVIEW'], true)) {
                throw new \DomainException('Conflict decision is stale or unavailable.');
            }
            if ($decision !== 'ACCEPT_SERVER' && $decision !== 'DISMISS') {
                throw new \DomainException('Client or manual proposals require authoritative domain revalidation and are not accepted by this generic endpoint.');
            }
            $emptyHash = hash('sha256', '', true);
            $this->prepare('INSERT INTO offline_conflict_decisions (public_id,workspace_id,conflict_id,decision_code,expected_conflict_version,safe_reason_code,proposal_schema_version,resolution_payload_ciphertext,resolution_payload_sha256,evidence_sha256,decided_by_account_id,decided_at,created_at) VALUES (:public,:workspace,:conflict,:decision,:version,:reason,NULL,NULL,:payload,:evidence,:actor,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))')->execute([':public' => UuidV7::generate()->toBinary(), ':workspace' => $workspaceId, ':conflict' => $row['id'], ':decision' => $decision, ':version' => $expectedVersion, ':reason' => $reason, ':payload' => $emptyHash, ':evidence' => hash('sha256', $conflict->toString() . '|' . $decision . '|' . $expectedVersion, true), ':actor' => $actor]);
            $this->prepare('UPDATE synchronization_conflicts SET status_code=:status,version=version+1,resolved_at=UTC_TIMESTAMP(6),updated_at=UTC_TIMESTAMP(6) WHERE id=:id')->execute([':status' => $status, ':id' => $row['id']]);
            $database->commit();
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    /** @param list<array{type:string,id:string,version:int,payload:array<string,mixed>}> $entities */
    public function preparePackage(int $workspaceId, UuidV7 $device, UuidV7 $edition, array $entities, int $actor): UuidV7
    {
        if (count($entities) > 5000) {
            throw new \OverflowException('Offline package contains too many entities.');
        }
        $database = $this->connections->connection();
        $database->beginTransaction();
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
            $database->commit();
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
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
        $statement = $this->prepare('INSERT IGNORE INTO offline_device_nonces (device_id,nonce_hash,request_hash,request_timestamp,expires_at,created_at) VALUES (:device,UNHEX(SHA2(:nonce,256)),UNHEX(:request),FROM_UNIXTIME(:timestamp),DATE_ADD(FROM_UNIXTIME(:timestamp),INTERVAL 10 MINUTE),UTC_TIMESTAMP(6))');
        $statement->execute([':device' => $authority['device_id'], ':nonce' => $nonce, ':request' => $requestHash, ':timestamp' => $timestamp]);
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
        $statement = $this->prepare('SELECT BIN_TO_UUID(p.public_id) public_id,p.package_code,p.schema_version,p.scope_version,p.status_code,p.manifest_canonical_json,p.manifest_sha256,p.artifact_sha256,p.signing_key_code,p.detached_signature,p.byte_size,p.generated_at,p.expires_at,a.nonce,a.ciphertext FROM offline_assignment_packages p INNER JOIN offline_package_artifacts a ON a.package_id=p.id WHERE p.workspace_id=:workspace AND p.id=:package');
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
            $this->prepare("UPDATE offline_assignment_packages SET status_code=IF(status_code='READY','DOWNLOADED',status_code),downloaded_at=COALESCE(downloaded_at,UTC_TIMESTAMP(6)),version=version+IF(status_code='READY',1,0),updated_at=UTC_TIMESTAMP(6) WHERE id=:package")->execute([':package' => $authority['package_id']]);
        }

        return $result;
    }

    /** @param array{workspace_id:int,venue_id:int,device_id:int,package_id:int,public_key:string,device_status:string,package_status:string,package_expires:string} $authority */
    public function activatePackage(array $authority): void
    {
        $statement = $this->prepare("UPDATE offline_assignment_packages SET status_code='ACTIVE',activated_at=COALESCE(activated_at,UTC_TIMESTAMP(6)),version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=:package AND workspace_id=:workspace AND status_code IN ('READY','DOWNLOADED') AND expires_at>UTC_TIMESTAMP(6)");
        $statement->execute([':package' => $authority['package_id'], ':workspace' => $authority['workspace_id']]);
        if ($statement->rowCount() !== 1) {
            throw new \DomainException('Offline package cannot be activated.');
        }
    }

    /** @param array{workspace_id:int,venue_id:int,device_id:int,package_id:int,public_key:string,device_status:string,package_status:string,package_expires:string} $authority */
    public function openSyncSession(array $authority, UuidV7 $clientSession): UuidV7
    {
        $existing = $this->prepare('SELECT public_id FROM synchronization_batches WHERE device_id=:device AND client_session_uuid=:client');
        $existing->execute([':device' => $authority['device_id'], ':client' => $clientSession->toBinary()]);
        $binary = $existing->fetchColumn();
        if (is_string($binary)) {
            return UuidV7::fromBinary($binary);
        }
        $id = UuidV7::generate();
        $this->prepare("INSERT INTO synchronization_batches (public_id,workspace_id,venue_id,device_id,offline_assignment_package_id,client_session_uuid,status_code,first_local_sequence,last_local_sequence,change_count,accepted_count,rejected_count,conflict_count,payload_bytes,version,opened_at,completed_at,failed_at,expires_at,safe_reason_code,created_at,updated_at) VALUES (:public,:workspace,:venue,:device,:package,:client,'OPENED',NULL,NULL,0,0,0,0,0,1,UTC_TIMESTAMP(6),NULL,NULL,DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 30 MINUTE),NULL,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))")->execute([':public' => $id->toBinary(), ':workspace' => $authority['workspace_id'], ':venue' => $authority['venue_id'], ':device' => $authority['device_id'], ':package' => $authority['package_id'], ':client' => $clientSession->toBinary()]);

        return $id;
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
        $database = $this->connections->connection();
        $database->beginTransaction();
        try {
            $batch = $this->oneForUpdate("SELECT id,status_code,change_count,payload_bytes FROM synchronization_batches WHERE workspace_id=:workspace AND device_id=:device AND public_id=:public AND status_code IN ('OPENED','RECEIVING') AND expires_at>UTC_TIMESTAMP(6) FOR UPDATE", [':workspace' => $authority['workspace_id'], ':device' => $authority['device_id'], ':public' => $session->toBinary()]);
            $replay = $this->prepare('SELECT result_status,safe_reason_code,BIN_TO_UUID(result_public_id) result_public_id FROM offline_sync_receipts WHERE device_id=:device AND client_change_uuid=:change');
            $replay->execute([':device' => $authority['device_id'], ':change' => $changeId->toBinary()]);
            $prior = $this->row($replay);
            if ($prior !== null) {
                $database->commit();
                return [
                    'change_id' => $changeId->toString(),
                    'status' => 'DUPLICATE',
                    'original_status' => $this->text($prior, 'result_status'),
                    'reason_code' => $this->nullableText($prior, 'safe_reason_code'),
                    'receipt_id' => $this->text($prior, 'result_public_id'),
                ];
            }
            $newBytes = $this->integer($batch, 'payload_bytes') + strlen($payloadJson);
            if ($this->integer($batch, 'change_count') >= 100 || $newBytes > 2_097_152) {
                throw new \OverflowException('Offline synchronization bounds exceeded.');
            }
            $submissionId = UuidV7::generate();
            $encrypted = $this->cryptography->encrypt($payloadJson, $changeId->toString());
            $accepted = in_array($operation, ['VENUE_INCIDENT_RECORDED', 'OPERATIONAL_NOTE_RECORDED', 'JUDGE_ACKNOWLEDGEMENT_RECORDED'], true);
            $resultStatus = $accepted ? 'ACCEPTED' : 'CONFLICT';
            $reason = $accepted ? null : 'AUTHORITATIVE_REVALIDATION_REQUIRED';
            $this->prepare('INSERT INTO offline_submission_events (public_id,workspace_id,offline_assignment_package_id,device_id,client_change_uuid,device_sequence,entity_type,entity_public_id,operation_code,expected_record_version,payload_schema_version,payload_ciphertext,payload_sha256,client_occurred_at,received_at,status_code) VALUES (:public,:workspace,:package,:device,:change,:sequence,:entity_type,:entity,:operation,:version,1,:ciphertext,UNHEX(:payload_hash),NULL,UTC_TIMESTAMP(6),:status)')->execute([':public' => $submissionId->toBinary(), ':workspace' => $authority['workspace_id'], ':package' => $authority['package_id'], ':device' => $authority['device_id'], ':change' => $changeId->toBinary(), ':sequence' => $sequence, ':entity_type' => $entityType, ':entity' => $entityId->toBinary(), ':operation' => $operation, ':version' => $expectedVersion, ':ciphertext' => $encrypted['nonce'] . $encrypted['ciphertext'], ':payload_hash' => $suppliedHash, ':status' => $resultStatus]);
            $submissionInternal = $this->lastInsertId();
            $this->prepare('INSERT INTO offline_sync_changes (public_id,workspace_id,sync_session_id,submission_event_id,local_sequence,payload_bytes,status_code,safe_reason_code,received_at,processed_at) VALUES (:public,:workspace,:session,:submission,:sequence,:bytes,:status,:reason,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))')->execute([':public' => UuidV7::generate()->toBinary(), ':workspace' => $authority['workspace_id'], ':session' => $batch['id'], ':submission' => $submissionInternal, ':sequence' => $sequence, ':bytes' => strlen($payloadJson), ':status' => $resultStatus, ':reason' => $reason]);
            $receiptId = UuidV7::generate();
            $this->prepare('INSERT INTO offline_sync_receipts (public_id,workspace_id,venue_id,device_id,sync_session_id,client_change_uuid,local_sequence,entity_type,entity_public_id,operation_code,result_status,server_entity_version,result_public_id,safe_reason_code,request_payload_sha256,result_payload_sha256,received_at,applied_at,created_at) VALUES (:public,:workspace,:venue,:device,:session,:change,:sequence,:entity_type,:entity,:operation,:status,:server_version,:result,:reason,UNHEX(:request_hash),UNHEX(SHA2(:result_hash,256)),UTC_TIMESTAMP(6),IF(:status="ACCEPTED",UTC_TIMESTAMP(6),NULL),UTC_TIMESTAMP(6))')->execute([':public' => $receiptId->toBinary(), ':workspace' => $authority['workspace_id'], ':venue' => $authority['venue_id'], ':device' => $authority['device_id'], ':session' => $batch['id'], ':change' => $changeId->toBinary(), ':sequence' => $sequence, ':entity_type' => $entityType, ':entity' => $entityId->toBinary(), ':operation' => $operation, ':status' => $resultStatus, ':server_version' => $accepted ? $expectedVersion + 1 : null, ':result' => UuidV7::generate()->toBinary(), ':reason' => $reason, ':request_hash' => $suppliedHash, ':result_hash' => $resultStatus . '|' . ($reason ?? '')]);
            if ($accepted) {
                $this->prepare('INSERT INTO offline_operation_events (public_id,workspace_id,venue_id,device_id,receipt_id,operation_code,entity_public_id,before_version,after_version,evidence_sha256,occurred_at) VALUES (:public,:workspace,:venue,:device,LAST_INSERT_ID(),:operation,:entity,:before,:after,UNHEX(SHA2(:evidence,256)),UTC_TIMESTAMP(6))')->execute([':public' => UuidV7::generate()->toBinary(), ':workspace' => $authority['workspace_id'], ':venue' => $authority['venue_id'], ':device' => $authority['device_id'], ':operation' => $operation, ':entity' => $entityId->toBinary(), ':before' => $expectedVersion, ':after' => $expectedVersion + 1, ':evidence' => $receiptId->toString() . '|' . $suppliedHash]);
            } else {
                $conflictId = UuidV7::generate();
                $this->prepare("INSERT INTO synchronization_conflicts (public_id,workspace_id,synchronization_batche_id,offline_submission_event_id,conflict_type,server_version,server_state_sha256,status_code,version,created_at,updated_at,assigned_at,resolved_at) VALUES (:public,:workspace,:session,:submission,'SOURCE_CHANGED',NULL,UNHEX(SHA2('AUTHORITATIVE_REVALIDATION_REQUIRED',256)),'OPEN',1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL,NULL)")->execute([':public' => $conflictId->toBinary(), ':workspace' => $authority['workspace_id'], ':session' => $batch['id'], ':submission' => $submissionInternal]);
            }
            $this->prepare("UPDATE synchronization_batches SET status_code='RECEIVING',first_local_sequence=COALESCE(first_local_sequence,:sequence),last_local_sequence=:sequence,change_count=change_count+1,accepted_count=accepted_count+:accepted,conflict_count=conflict_count+:conflict,payload_bytes=:bytes,version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=:id")->execute([':sequence' => $sequence, ':accepted' => $accepted ? 1 : 0, ':conflict' => $accepted ? 0 : 1, ':bytes' => $newBytes, ':id' => $batch['id']]);
            $database->commit();

            return ['change_id' => $changeId->toString(), 'status' => $resultStatus, 'reason_code' => $reason, 'receipt_id' => $receiptId->toString()];
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    /**
     * @param array{workspace_id:int,venue_id:int,device_id:int,package_id:int,public_key:string,device_status:string,package_status:string,package_expires:string} $authority
     * @return array<string,int|string>
     */
    public function completeSyncSession(array $authority, UuidV7 $session): array
    {
        $statement = $this->prepare("UPDATE synchronization_batches SET status_code=IF(conflict_count>0,'CONFLICTED','COMPLETED'),completed_at=UTC_TIMESTAMP(6),version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE workspace_id=:workspace AND device_id=:device AND public_id=:public AND status_code IN ('OPENED','RECEIVING')");
        $statement->execute([':workspace' => $authority['workspace_id'], ':device' => $authority['device_id'], ':public' => $session->toBinary()]);
        if ($statement->rowCount() !== 1) {
            throw new \DomainException('Synchronization session cannot be completed.');
        }
        $rows = $this->queryRows('SELECT id,status_code,accepted_count,rejected_count,conflict_count,first_local_sequence,last_local_sequence FROM synchronization_batches WHERE workspace_id=:workspace AND public_id=:public', [':workspace' => $authority['workspace_id'], ':public' => $session->toBinary()]);
        $row = $rows[0] ?? null;
        if ($row === null) {
            throw new \DomainException('Synchronization session is unavailable.');
        }
        $evidence = $this->canonicalJson->encode($row);
        $this->prepare("INSERT INTO venue_reconciliation_reports (public_id,workspace_id,synchronization_batche_id,accepted_count,rejected_count,conflict_count,first_sequence,last_sequence,evidence_sha256,outcome_code,completed_at,created_at) VALUES (:public,:workspace,:session,:accepted,:rejected,:conflicts,:first,:last,UNHEX(SHA2(:evidence,256)),:outcome,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))")->execute([':public' => UuidV7::generate()->toBinary(), ':workspace' => $authority['workspace_id'], ':session' => $row['id'], ':accepted' => $row['accepted_count'], ':rejected' => $row['rejected_count'], ':conflicts' => $row['conflict_count'], ':first' => $row['first_local_sequence'], ':last' => $row['last_local_sequence'], ':evidence' => $evidence, ':outcome' => $this->integer($row, 'conflict_count') > 0 ? 'DRIFT' : 'PASS']);
        $this->prepare('UPDATE venue_edge_node_registrations SET last_sync_at=UTC_TIMESTAMP(6),updated_at=UTC_TIMESTAMP(6) WHERE id=:device')->execute([':device' => $authority['device_id']]);

        return [
            'status' => $this->text($row, 'status_code'),
            'accepted' => $this->integer($row, 'accepted_count'),
            'rejected' => $this->integer($row, 'rejected_count'),
            'conflicts' => $this->integer($row, 'conflict_count'),
        ];
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
