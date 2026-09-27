<?php

declare(strict_types=1);

namespace Qmdb\Modules\ProductionHardening\Infrastructure\Persistence;

use PDO;
use PDOStatement;
use Qmdb\Modules\ProductionHardening\Domain\ApiCredentialIssuer;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class MySqlProductionHardeningRepository
{
    private const array TABLES = [
        'notifications', 'notification_preferences', 'notification_templates', 'notification_deliveries',
        'notification_delivery_attempts', 'notification_dead_letters', 'api_clients', 'api_client_scopes',
        'api_credentials', 'webhook_subscriptions', 'webhook_deliveries', 'webhook_delivery_attempts',
        'integration_events', 'external_provider_references', 'processing_purposes', 'privacy_notice_versions',
        'privacy_requests', 'privacy_request_events', 'privacy_request_assignments', 'retention_policy_records',
        'data_holds', 'anonymization_events', 'data_export_deliveries', 'audit_events', 'audit_checkpoints',
        'audit_verification_runs', 'outbox_events', 'idempotency_records', 'operational_service_catalog',
        'operational_sli_definitions', 'operational_metric_samples', 'operational_alert_intents',
        'security_incidents', 'incident_events', 'key_rotation_records', 'backup_artifacts',
        'backup_verification_runs', 'restore_verification_runs', 'p12_operation_receipts',
    ];

    private const array MIGRATIONS = [
        '20260927100000_create_notifications_integrations',
        '20260927101000_create_privacy_security_operations',
        '20260927102000_create_audit_outbox_idempotency',
        '20260927103000_create_operational_assurance',
    ];

    public function __construct(private DatabaseConnectionProvider $connections, private ApiCredentialIssuer $credentials)
    {
    }

    /** @return array{tables:int,migrations:int,permissions:int,purposes:int,notices:int,templates:int,policies:int,services:int,slis:int} */
    public function verifyFoundation(): array
    {
        $tables = $this->column('SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE()');
        foreach (self::TABLES as $table) {
            if (!in_array($table, $tables, true)) {
                throw new \RuntimeException('Required P12 table is missing: ' . $table);
            }
        }
        $applied = $this->column("SELECT migration_id FROM qmdb_schema_migrations WHERE status='APPLIED'");
        foreach (self::MIGRATIONS as $migration) {
            if (!in_array($migration, $applied, true)) {
                throw new \RuntimeException('Required P12 migration is not applied: ' . $migration);
            }
        }
        if ($this->count("SELECT COUNT(*) FROM qmdb_schema_seeds WHERE seed_id='20260927110000_seed_p12_production_hardening_catalog' AND status='APPLIED'") !== 1) {
            throw new \RuntimeException('P12 governed catalog seed is not applied.');
        }
        $result = [
            'tables' => count(self::TABLES), 'migrations' => count(self::MIGRATIONS),
            'permissions' => $this->count("SELECT COUNT(*) FROM authorization_permissions WHERE owning_module='production.hardening' AND status='ACTIVE'"),
            'purposes' => $this->count('SELECT COUNT(*) FROM processing_purposes WHERE effective_until IS NULL'),
            'notices' => $this->count("SELECT COUNT(*) FROM privacy_notice_versions WHERE status_code='ACTIVE'"),
            'templates' => $this->count(
                "SELECT COUNT(*) FROM notification_templates WHERE status_code='ACTIVE'"
                . " AND template_code IN ('PRIVACY_REQUEST_STATUS','SECURITY_INCIDENT_NOTICE','INTEGRATION_SUSPENDED')",
            ),
            'policies' => $this->count("SELECT COUNT(*) FROM retention_policy_records WHERE status_code='ACTIVE'"),
            'services' => $this->count("SELECT COUNT(*) FROM operational_service_catalog WHERE status_code='ACTIVE'"),
            'slis' => $this->count("SELECT COUNT(*) FROM operational_sli_definitions WHERE status_code='ACTIVE'"),
        ];
        if ($result['permissions'] !== 16 || $result['purposes'] !== 4 || $result['notices'] !== 4 || $result['templates'] !== 6 || $result['policies'] !== 4 || $result['services'] !== 6 || $result['slis'] !== 5) {
            throw new \RuntimeException('P12 governed catalogs are incomplete.');
        }
        return $result;
    }

    /** @return array<string,int> */
    public function operationalSummary(): array
    {
        return [
            'api_clients' => $this->count("SELECT COUNT(*) FROM api_clients WHERE status_code='ACTIVE'"),
            'webhooks_active' => $this->count("SELECT COUNT(*) FROM webhook_subscriptions WHERE status_code='ACTIVE'"),
            'webhook_due' => $this->count("SELECT COUNT(*) FROM webhook_deliveries WHERE status_code IN ('PENDING','RETRY') AND available_at<=UTC_TIMESTAMP(6)"),
            'notification_due' => $this->count("SELECT COUNT(*) FROM notification_deliveries WHERE status_code IN ('PENDING','RETRY') AND available_at<=UTC_TIMESTAMP(6)"),
            'outbox_due' => $this->count("SELECT COUNT(*) FROM outbox_events WHERE status_code IN ('PENDING','RETRY') AND available_at<=UTC_TIMESTAMP(6)"),
            'privacy_open' => $this->count("SELECT COUNT(*) FROM privacy_requests WHERE status_code NOT IN ('CLOSED','REJECTED','DELIVERED')"),
            'incidents_open' => $this->count("SELECT COUNT(*) FROM security_incidents WHERE status_code<>'CLOSED'"),
            'alerts_open' => $this->count("SELECT COUNT(*) FROM operational_alert_intents WHERE status_code IN ('OPEN','ACKNOWLEDGED')"),
            'backup_artifacts' => $this->count("SELECT COUNT(*) FROM backup_artifacts WHERE status_code IN ('REGISTERED','VERIFIED')"),
        ];
    }

    /** @return list<array<string,mixed>> */
    public function notifications(int $accountId, int $afterId = 0, int $limit = 25): array
    {
        $limit = max(1, min(50, $limit));
        $statement = $this->prepare("SELECT id,BIN_TO_UUID(public_id) AS public_id,safe_subject,safe_body,source_uri,status_code,read_at,created_at,expires_at FROM notifications WHERE recipient_account_id=:account AND id>:after AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP(6)) ORDER BY id ASC LIMIT {$limit}");
        $statement->execute([':account' => $accountId, ':after' => $afterId]);
        return $this->rows($statement);
    }

    public function markNotificationRead(int $accountId, UuidV7 $notificationId): bool
    {
        $statement = $this->prepare('UPDATE notifications SET read_at=COALESCE(read_at,UTC_TIMESTAMP(6)),updated_at=UTC_TIMESTAMP(6) WHERE public_id=:public AND recipient_account_id=:account');
        $statement->execute([':public' => $notificationId->toBinary(), ':account' => $accountId]);
        return $statement->rowCount() === 1;
    }

    /**
     * @param list<string> $scopes
     * @return array{client_id:string,credential:string,expires_at:string}
     */
    public function createApiClient(?int $workspaceId, int $ownerAccountId, string $clientCode, string $displayName, array $scopes): array
    {
        if (preg_match('/\A[a-z0-9][a-z0-9._-]{2,79}\z/', $clientCode) !== 1 || trim($displayName) === '' || mb_strlen($displayName) > 191) {
            throw new \InvalidArgumentException('API client identity is invalid.');
        }
        $allowed = ['projections.results.read', 'projections.certificates.read', 'notifications.status.read', 'webhooks.manage'];
        $scopes = array_values(array_unique($scopes));
        if ($scopes === [] || array_diff($scopes, $allowed) !== []) {
            throw new \InvalidArgumentException('API client scopes are invalid.');
        }
        $issued = $this->credentials->issue();
        $clientId = UuidV7::generate();
        $credentialId = UuidV7::generate();
        $expires = new \DateTimeImmutable('+90 days', new \DateTimeZone('UTC'));
        $database = $this->connections->connection();
        $database->beginTransaction();
        try {
            $insert = $this->prepare("INSERT INTO api_clients (public_id,workspace_id,client_code,display_name,owner_account_id,status_code,quota_per_minute,allowed_cidrs_json,version,created_at,activated_at,suspended_at,revoked_at,updated_at) VALUES (:public,:workspace,:code,:name,:owner,'ACTIVE',60,JSON_ARRAY(),1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL,NULL,UTC_TIMESTAMP(6))");
            $insert->execute([':public' => $clientId->toBinary(), ':workspace' => $workspaceId, ':code' => $clientCode, ':name' => trim($displayName), ':owner' => $ownerAccountId]);
            $internalId = (int) $database->lastInsertId();
            $scope = $this->prepare('INSERT INTO api_client_scopes (api_client_id,scope_code,granted_by_account_id,granted_at,revoked_at) VALUES (:client,:scope,:actor,UTC_TIMESTAMP(6),NULL)');
            foreach ($scopes as $scopeCode) {
                $scope->execute([':client' => $internalId, ':scope' => $scopeCode, ':actor' => $ownerAccountId]);
            }
            $credential = $this->prepare("INSERT INTO api_credentials (public_id,api_client_id,key_id,secret_hash,status_code,not_before,expires_at,last_used_at,rotated_from_id,created_at,revoked_at) VALUES (:public,:client,:key,:hash,'ACTIVE',UTC_TIMESTAMP(6),:expires,NULL,NULL,UTC_TIMESTAMP(6),NULL)");
            $credential->execute([':public' => $credentialId->toBinary(), ':client' => $internalId, ':key' => $issued['key_id'], ':hash' => $issued['secret_hash'], ':expires' => $expires->format('Y-m-d H:i:s.u')]);
            $database->commit();
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }
        return ['client_id' => $clientId->toString(), 'credential' => $issued['key_id'] . '.' . $issued['secret'], 'expires_at' => $expires->format(DATE_ATOM)];
    }

    /** @return list<array<string,mixed>> */
    public function apiClients(?int $workspaceId): array
    {
        $statement = $this->prepare('SELECT BIN_TO_UUID(public_id) AS public_id,client_code,display_name,status_code,quota_per_minute,created_at,activated_at,suspended_at,revoked_at FROM api_clients WHERE ((:workspace_null IS NULL AND workspace_id IS NULL) OR workspace_id=:workspace) ORDER BY id DESC LIMIT 50');
        $statement->bindValue(':workspace_null', $workspaceId, $workspaceId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':workspace', $workspaceId, $workspaceId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->execute();
        return $this->rows($statement);
    }

    /** @return array{credential:string,expires_at:string} */
    public function rotateApiCredential(?int $workspaceId, UuidV7 $clientId): array
    {
        $database = $this->connections->connection();
        $database->beginTransaction();
        try {
            $client = $this->prepare("SELECT id FROM api_clients WHERE public_id=:public AND status_code='ACTIVE' AND ((:workspace_null IS NULL AND workspace_id IS NULL) OR workspace_id=:workspace) FOR UPDATE");
            $client->bindValue(':public', $clientId->toBinary(), PDO::PARAM_LOB);
            $client->bindValue(':workspace_null', $workspaceId, $workspaceId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $client->bindValue(':workspace', $workspaceId, $workspaceId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $client->execute();
            $internal = $client->fetchColumn();
            if (!is_int($internal) && !is_string($internal)) {
                throw new \DomainException('API client is unavailable.');
            }
            $previous = $this->prepare("SELECT id FROM api_credentials WHERE api_client_id=:client AND status_code='ACTIVE' ORDER BY id DESC LIMIT 1 FOR UPDATE");
            $previous->execute([':client' => (int) $internal]);
            $previousId = $previous->fetchColumn();
            $issued = $this->credentials->issue();
            $expires = new \DateTimeImmutable('+90 days', new \DateTimeZone('UTC'));
            $this->prepare("INSERT INTO api_credentials (public_id,api_client_id,key_id,secret_hash,status_code,not_before,expires_at,last_used_at,rotated_from_id,created_at,revoked_at) VALUES (:public,:client,:key,:hash,'ACTIVE',UTC_TIMESTAMP(6),:expires,NULL,:previous,UTC_TIMESTAMP(6),NULL)")
                ->execute([':public' => UuidV7::generate()->toBinary(), ':client' => (int) $internal, ':key' => $issued['key_id'], ':hash' => $issued['secret_hash'], ':expires' => $expires->format('Y-m-d H:i:s.u'), ':previous' => is_int($previousId) || is_string($previousId) ? (int) $previousId : null]);
            if (is_int($previousId) || is_string($previousId)) {
                $this->prepare("UPDATE api_credentials SET status_code='ROTATING',expires_at=LEAST(expires_at,DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 24 HOUR)) WHERE id=:id")->execute([':id' => (int) $previousId]);
            }
            $database->commit();
            return ['credential' => $issued['key_id'] . '.' . $issued['secret'], 'expires_at' => $expires->format(DATE_ATOM)];
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    public function revokeApiClient(?int $workspaceId, UuidV7 $clientId): bool
    {
        $statement = $this->prepare("UPDATE api_clients SET status_code='REVOKED',revoked_at=UTC_TIMESTAMP(6),updated_at=UTC_TIMESTAMP(6) WHERE public_id=:public AND status_code IN ('PENDING','ACTIVE','SUSPENDED') AND ((:workspace_null IS NULL AND workspace_id IS NULL) OR workspace_id=:workspace)");
        $statement->bindValue(':public', $clientId->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':workspace_null', $workspaceId, $workspaceId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':workspace', $workspaceId, $workspaceId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->execute();
        if ($statement->rowCount() === 1) {
            $this->prepare("UPDATE api_credentials k INNER JOIN api_clients c ON c.id=k.api_client_id SET k.status_code='REVOKED',k.revoked_at=UTC_TIMESTAMP(6) WHERE c.public_id=:public AND k.status_code IN ('ACTIVE','ROTATING')")->execute([':public' => $clientId->toBinary()]);
            return true;
        }
        return false;
    }

    public function createWebhookSubscription(?int $workspaceId, UuidV7 $clientId, string $eventCode, string $endpoint, string $keyId, string $encryptedSecret): UuidV7
    {
        $allowedEvents = ['competition.result.published', 'certificate.issued', 'certificate.revoked', 'privacy.request.updated', 'security.incident.updated'];
        if (!in_array($eventCode, $allowedEvents, true)) {
            throw new \InvalidArgumentException('Webhook event is not allowlisted.');
        }
        $statement = $this->prepare("SELECT c.id FROM api_clients c INNER JOIN api_client_scopes s ON s.api_client_id=c.id AND s.scope_code='webhooks.manage' AND s.revoked_at IS NULL WHERE c.public_id=:public AND c.status_code='ACTIVE' AND ((:workspace_null IS NULL AND c.workspace_id IS NULL) OR c.workspace_id=:workspace)");
        $statement->bindValue(':public', $clientId->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':workspace_null', $workspaceId, $workspaceId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':workspace', $workspaceId, $workspaceId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->execute();
        $internal = $statement->fetchColumn();
        if (!is_int($internal) && !is_string($internal)) {
            throw new \DomainException('API client cannot manage webhooks.');
        }
        $public = UuidV7::generate();
        $this->prepare("INSERT INTO webhook_subscriptions (public_id,workspace_id,api_client_id,event_code,endpoint_url,endpoint_hash,signing_key_id,signing_secret_ciphertext,payload_classification,status_code,failure_count,version,verified_at,created_at,updated_at,suspended_at) VALUES (:public,:workspace,:client,:event,:endpoint,UNHEX(SHA2(:endpoint_hash_source,256)),:key,:secret,'INTERNAL','ACTIVE',0,1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL)")
            ->execute([':public' => $public->toBinary(), ':workspace' => $workspaceId, ':client' => (int) $internal, ':event' => $eventCode, ':endpoint' => $endpoint, ':endpoint_hash_source' => $endpoint, ':key' => $keyId, ':secret' => $encryptedSecret]);
        return $public;
    }

    /** @return list<array<string,mixed>> */
    public function webhookSubscriptions(?int $workspaceId): array
    {
        $statement = $this->prepare('SELECT BIN_TO_UUID(s.public_id) AS public_id,c.client_code,s.event_code,s.endpoint_url,s.status_code,s.failure_count,s.created_at,s.suspended_at FROM webhook_subscriptions s INNER JOIN api_clients c ON c.id=s.api_client_id WHERE ((:workspace_null IS NULL AND s.workspace_id IS NULL) OR s.workspace_id=:workspace) ORDER BY s.id DESC LIMIT 50');
        $statement->bindValue(':workspace_null', $workspaceId, $workspaceId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':workspace', $workspaceId, $workspaceId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->execute();
        return $this->rows($statement);
    }

    public function suspendWebhook(?int $workspaceId, UuidV7 $subscriptionId): bool
    {
        $statement = $this->prepare("UPDATE webhook_subscriptions SET status_code='SUSPENDED',suspended_at=UTC_TIMESTAMP(6),updated_at=UTC_TIMESTAMP(6),version=version+1 WHERE public_id=:public AND status_code='ACTIVE' AND ((:workspace_null IS NULL AND workspace_id IS NULL) OR workspace_id=:workspace)");
        $statement->bindValue(':public', $subscriptionId->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':workspace_null', $workspaceId, $workspaceId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':workspace', $workspaceId, $workspaceId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->execute();
        return $statement->rowCount() === 1;
    }

    /** @return array{id:int,public_id:string,workspace_id:?int,quota:int,scopes:list<string>}|null */
    public function authenticateApiCredential(string $credential): ?array
    {
        $parts = explode('.', $credential, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }
        $statement = $this->prepare("SELECT c.id,BIN_TO_UUID(c.public_id) AS public_id,c.workspace_id,c.quota_per_minute,k.id AS credential_id,k.secret_hash FROM api_credentials k INNER JOIN api_clients c ON c.id=k.api_client_id WHERE k.key_id=:key AND k.status_code='ACTIVE' AND k.not_before<=UTC_TIMESTAMP(6) AND k.expires_at>UTC_TIMESTAMP(6) AND c.status_code='ACTIVE'");
        $statement->execute([':key' => $parts[0]]);
        $row = $this->row($statement);
        if ($row === null || !is_string($row['secret_hash'] ?? null) || !$this->credentials->verify($parts[1], $row['secret_hash'])) {
            return null;
        }
        $scopesStatement = $this->prepare('SELECT scope_code FROM api_client_scopes WHERE api_client_id=:client AND revoked_at IS NULL ORDER BY scope_code');
        $clientInternalId = $this->integer($row, 'id');
        $credentialInternalId = $this->integer($row, 'credential_id');
        $scopesStatement->execute([':client' => $clientInternalId]);
        $scopes = $scopesStatement->fetchAll(PDO::FETCH_COLUMN);
        $this->prepare('UPDATE api_credentials SET last_used_at=UTC_TIMESTAMP(6) WHERE id=:id')->execute([':id' => $credentialInternalId]);
        return [
            'id' => $clientInternalId, 'public_id' => $this->text($row, 'public_id'),
            'workspace_id' => $this->nullableInteger($row, 'workspace_id'),
            'quota' => $this->integer($row, 'quota_per_minute'),
            'scopes' => array_values(array_filter($scopes, 'is_string')),
        ];
    }

    /** @param array{id:int,public_id:string,workspace_id:?int,quota:int,scopes:list<string>} $client */
    public function claimApiRequest(array $client, string $operation, string $nonce, string $requestHash, int $timestamp): bool
    {
        if (abs(time() - $timestamp) > 300 || preg_match('/\A[A-Za-z0-9_-]{16,128}\z/', $nonce) !== 1) {
            return false;
        }
        $recent = $this->countPrepared('SELECT COUNT(*) FROM idempotency_records WHERE actor_kind=\'API_CLIENT\' AND actor_reference=:actor AND created_at>=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 MINUTE)', [':actor' => $client['public_id']]);
        if ($recent >= $client['quota']) {
            throw new \OverflowException('API quota exceeded.');
        }
        try {
            $this->prepare("INSERT INTO idempotency_records (public_id,workspace_id,actor_kind,actor_reference,operation_code,idempotency_key_hash,request_hash,response_status,response_reference,status_code,created_at,completed_at,expires_at) VALUES (:public,:workspace,'API_CLIENT',:actor,:operation,UNHEX(SHA2(:nonce,256)),UNHEX(:request_hash),NULL,NULL,'PROCESSING',UTC_TIMESTAMP(6),NULL,DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 10 MINUTE))")
                ->execute([':public' => UuidV7::generate()->toBinary(), ':workspace' => $client['workspace_id'], ':actor' => $client['public_id'], ':operation' => $operation, ':nonce' => $nonce, ':request_hash' => $requestHash]);
            return true;
        } catch (\PDOException $error) {
            if ((string) $error->getCode() === '23000') {
                return false;
            }
            throw $error;
        }
    }

    public function claimAccountOperation(
        int $accountId,
        ?int $workspaceId,
        string $operation,
        string $submissionId,
        string $requestHash,
    ): bool {
        if (
            preg_match('/\A[0-9a-f-]{36}\z/i', $submissionId) !== 1
            || preg_match('/\A[a-f0-9]{64}\z/', $requestHash) !== 1
        ) {
            throw new \InvalidArgumentException('Operation receipt is invalid.');
        }
        try {
            $this->prepare("INSERT INTO idempotency_records (public_id,workspace_id,actor_kind,actor_reference,operation_code,idempotency_key_hash,request_hash,response_status,response_reference,status_code,created_at,completed_at,expires_at) VALUES (:public,:workspace,'ACCOUNT',:actor,:operation,UNHEX(SHA2(:submission,256)),UNHEX(:request_hash),NULL,NULL,'PROCESSING',UTC_TIMESTAMP(6),NULL,DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 24 HOUR))")
                ->execute([':public' => UuidV7::generate()->toBinary(), ':workspace' => $workspaceId, ':actor' => (string) $accountId, ':operation' => $operation, ':submission' => strtolower($submissionId), ':request_hash' => $requestHash]);
            return true;
        } catch (\PDOException $error) {
            if ((string) $error->getCode() === '23000') {
                return false;
            }
            throw $error;
        }
    }

    public function completeAccountOperation(
        int $accountId,
        string $operation,
        string $submissionId,
        int $responseStatus,
        bool $succeeded,
    ): void {
        $statement = $this->prepare("UPDATE idempotency_records SET response_status=:response,status_code=:status,completed_at=UTC_TIMESTAMP(6) WHERE actor_kind='ACCOUNT' AND actor_reference=:actor AND operation_code=:operation AND idempotency_key_hash=UNHEX(SHA2(:submission,256)) AND status_code='PROCESSING'");
        $statement->execute([
            ':response' => $responseStatus,
            ':status' => $succeeded ? 'COMPLETED' : 'FAILED',
            ':actor' => (string) $accountId,
            ':operation' => $operation,
            ':submission' => strtolower($submissionId),
        ]);
    }

    /** @return list<array<string,mixed>> */
    public function apiResultProjections(?int $workspaceId, int $afterId, int $limit = 25): array
    {
        $limit = max(1, min(50, $limit));
        $scope = $workspaceId === null ? "visibility_code='PUBLIC'" : "(visibility_code='PUBLIC' OR (visibility_code='WORKSPACE' AND workspace_id=:workspace))";
        $statement = $this->prepare("SELECT id,BIN_TO_UUID(public_id) AS id,status_code,provenance_code,locale,title,summary,source_updated_at,projected_at FROM search_projection_documents WHERE retired_at IS NULL AND source_kind='COMPETITION_RESULT' AND {$scope} AND id>:after ORDER BY id ASC LIMIT {$limit}");
        $parameters = [':after' => max(0, $afterId)];
        if ($workspaceId !== null) {
            $parameters[':workspace'] = $workspaceId;
        }
        $statement->execute($parameters);
        return $this->rows($statement);
    }

    public function createPrivacyRequest(int $requesterAccountId, ?int $workspaceId, UuidV7 $subjectId, string $type, string $authority, int $deadlineDays = 30): UuidV7
    {
        $allowedTypes = ['ACCESS', 'CORRECTION', 'EXPORT', 'ERASURE', 'RESTRICTION', 'OBJECTION', 'CHILD_DATA'];
        $allowedAuthorities = ['SELF', 'GUARDIAN', 'AUTHORIZED_REPRESENTATIVE'];
        if (!in_array($type, $allowedTypes, true) || !in_array($authority, $allowedAuthorities, true)) {
            throw new \InvalidArgumentException('Privacy request type or authority is invalid.');
        }
        $public = UuidV7::generate();
        $database = $this->connections->connection();
        $database->beginTransaction();
        try {
            $this->prepare("INSERT INTO privacy_requests (public_id,workspace_id,requester_account_id,subject_person_public_id,guardian_person_public_id,request_type,authority_code,scope_json,status_code,identity_verified_at,due_at,decision_code,decision_reason_code,version,created_at,updated_at,closed_at) VALUES (:public,:workspace,:requester,:subject,NULL,:type,:authority,JSON_OBJECT(),'IDENTITY_PENDING',NULL,DATE_ADD(UTC_TIMESTAMP(6),INTERVAL :days DAY),NULL,NULL,1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL)")
                ->execute([':public' => $public->toBinary(), ':workspace' => $workspaceId, ':requester' => $requesterAccountId, ':subject' => $subjectId->toBinary(), ':type' => $type, ':authority' => $authority, ':days' => max(1, min(90, $deadlineDays))]);
            $internal = (int) $database->lastInsertId();
            $eventPublic = UuidV7::generate();
            $hash = hash('sha256', $public->toString() . '|RECEIVED|' . $requesterAccountId, true);
            $this->prepare("INSERT INTO privacy_request_events (public_id,privacy_request_id,actor_account_id,event_code,safe_metadata_json,correlation_id,occurred_at,previous_hash,event_hash) VALUES (:public,:request,:actor,'RECEIVED',JSON_OBJECT(),:correlation,UTC_TIMESTAMP(6),NULL,:hash)")
                ->execute([':public' => $eventPublic->toBinary(), ':request' => $internal, ':actor' => $requesterAccountId, ':correlation' => $public->toString(), ':hash' => $hash]);
            $database->commit();
        } catch (\Throwable $error) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }
        return $public;
    }

    /** @return list<array<string,mixed>> */
    public function privacyRequests(int $accountId, bool $platform, ?int $workspaceId): array
    {
        if ($platform) {
            $statement = $this->prepare('SELECT BIN_TO_UUID(public_id) AS public_id,request_type,authority_code,status_code,due_at,created_at FROM privacy_requests ORDER BY id DESC LIMIT 50');
            $statement->execute();
            return $this->rows($statement);
        }
        $statement = $this->prepare('SELECT BIN_TO_UUID(public_id) AS public_id,request_type,authority_code,status_code,due_at,created_at FROM privacy_requests WHERE requester_account_id=:account AND ((:workspace_null IS NULL AND workspace_id IS NULL) OR workspace_id=:workspace) ORDER BY id DESC LIMIT 50');
        $statement->bindValue(':account', $accountId, PDO::PARAM_INT);
        $statement->bindValue(':workspace_null', $workspaceId, $workspaceId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':workspace', $workspaceId, $workspaceId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->execute();
        return $this->rows($statement);
    }

    /** @return array{examined:int,eligible:int,held:int} */
    public function retentionDryRun(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $statement = $this->prepare("SELECT n.id,EXISTS(SELECT 1 FROM data_holds h WHERE h.status_code='ACTIVE' AND h.subject_kind='NOTIFICATION' AND (h.subject_public_id IS NULL OR h.subject_public_id=n.public_id) AND (h.expires_at IS NULL OR h.expires_at>UTC_TIMESTAMP(6))) AS held FROM notifications n INNER JOIN retention_policy_records p ON p.policy_code='NOTIFICATION_OPERATIONAL' AND p.status_code='ACTIVE' WHERE n.created_at<DATE_SUB(UTC_TIMESTAMP(6),INTERVAL p.retention_days DAY) ORDER BY n.id LIMIT {$limit}");
        $statement->execute();
        $rows = $this->rows($statement);
        $held = count(array_filter($rows, fn (array $row): bool => $this->integer($row, 'held') === 1));
        return ['examined' => count($rows), 'eligible' => count($rows) - $held, 'held' => $held];
    }

    /** @return array{key:string,type:string} */
    public function verifyDueQueryPlan(): array
    {
        $statement = $this->prepare("EXPLAIN SELECT id FROM webhook_deliveries FORCE INDEX(ix_p12_webhook_delivery_due) WHERE status_code IN ('PENDING','RETRY') AND available_at<=UTC_TIMESTAMP(6) ORDER BY id LIMIT 25");
        $statement->execute();
        $plan = $this->row($statement);
        if ($plan === null || ($plan['key'] ?? null) !== 'ix_p12_webhook_delivery_due') {
            throw new \RuntimeException('P12 webhook due-work query does not use its bounded index.');
        }
        return ['key' => 'ix_p12_webhook_delivery_due', 'type' => $this->text($plan, 'type')];
    }

    public function database(): PDO
    {
        return $this->connections->connection();
    }

    private function count(string $sql): int
    {
        $statement = $this->connections->connection()->query($sql);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('P12 count query failed.');
        }
        return (int) $statement->fetchColumn();
    }

    /** @param array<string,mixed> $parameters */
    private function countPrepared(string $sql, array $parameters): int
    {
        $statement = $this->prepare($sql);
        $statement->execute($parameters);
        return (int) $statement->fetchColumn();
    }

    /** @return list<string> */
    private function column(string $sql): array
    {
        $statement = $this->connections->connection()->query($sql);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('P12 inventory query failed.');
        }
        return array_values(array_filter($statement->fetchAll(PDO::FETCH_COLUMN), 'is_string'));
    }

    private function prepare(string $sql): PDOStatement
    {
        $statement = $this->connections->connection()->prepare($sql);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('P12 database statement could not be prepared.');
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
            throw new \RuntimeException('P12 database returned an invalid row.');
        }
        return $this->normalizeRow($row);
    }

    /** @return list<array<string,mixed>> */
    private function rows(PDOStatement $statement): array
    {
        $rows = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            if (!is_array($row)) {
                throw new \RuntimeException('P12 database returned an invalid row.');
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
                throw new \RuntimeException('P12 database row contained a non-string column key.');
            }
            $normalized[$key] = $value;
        }
        return $normalized;
    }

    /** @param array<string,mixed> $row */
    private function integer(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        if (!is_int($value) && !(is_string($value) && preg_match('/\A[0-9]+\z/', $value) === 1)) {
            throw new \RuntimeException('P12 database integer column is invalid.');
        }
        return (int) $value;
    }

    /** @param array<string,mixed> $row */
    private function nullableInteger(array $row, string $key): ?int
    {
        return ($row[$key] ?? null) === null ? null : $this->integer($row, $key);
    }

    /** @param array<string,mixed> $row */
    private function text(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value)) {
            throw new \RuntimeException('P12 database text column is invalid.');
        }
        return $value;
    }
}
