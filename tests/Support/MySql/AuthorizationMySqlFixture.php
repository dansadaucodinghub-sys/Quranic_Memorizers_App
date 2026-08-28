<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\MySql;

use DateTimeImmutable;
use PDO;
use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationMethod;
use Qmdb\Modules\IdentityMultiFactor\Domain\SessionAuthenticationAssurance;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\IdentitySessions\Domain\DeviceId;
use Qmdb\Modules\IdentitySessions\Domain\SessionId;
use Qmdb\Modules\SecurityAuthorization\Domain\PlatformRoleAssignmentId;
use Qmdb\Modules\SecurityAuthorization\Domain\WorkspaceRoleAssignmentId;
use Qmdb\Modules\Tenancy\Application\TenantContext;
use Qmdb\Modules\Tenancy\Domain\Value\WorkspaceId;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Schema\Migration\MigrationRegistry;
use Qmdb\Shared\Schema\Seed\SeedRegistry;

final readonly class AuthorizationMySqlFixture
{
    public function __construct(private PDO $connection, private string $projectRoot)
    {
    }

    public function rebuild(): void
    {
        $this->dropBusinessTables();
        $factory = require $this->projectRoot . '/database/migrations.php';
        if (!is_callable($factory)) {
            throw new \RuntimeException('Migration fixture factory is invalid.');
        }
        $registry = $factory();
        if (!$registry instanceof MigrationRegistry) {
            throw new \RuntimeException('Migration fixture registry is invalid.');
        }
        foreach ($registry->ordered() as $migration) {
            foreach ($migration->up() as $step) {
                $this->connection->prepare($step->sql())->execute($step->parameters());
            }
        }
        $seedFactory = require $this->projectRoot . '/database/seeds.php';
        if (!is_callable($seedFactory)) {
            throw new \RuntimeException('Seed fixture factory is invalid.');
        }
        $seeds = $seedFactory();
        if (!$seeds instanceof SeedRegistry) {
            throw new \RuntimeException('Seed fixture registry is invalid.');
        }
        foreach ($seeds->ordered() as $seed) {
            foreach ($seed->steps() as $step) {
                $this->connection->prepare($step->sql())->execute($step->parameters());
            }
        }
    }

    public function dropBusinessTables(): void
    {
        foreach (
            [
            'workspace_role_assignments', 'platform_role_assignments', 'authorization_role_permissions',
            'authorization_roles', 'authorization_permissions', 'account_webauthn_ceremonies',
            'account_passkey_credentials', 'account_webauthn_user_handles', 'account_recovery_codes',
            'account_recovery_code_sets', 'account_totp_authenticators', 'account_step_up_grants',
            'account_authentication_transactions', 'account_mfa_policies',
            'account_security_notification_events', 'account_security_notifications',
            'account_password_recovery_events', 'account_password_recovery_challenges', 'user_sessions',
            'user_devices', 'identity_rate_limit_buckets', 'account_email_verification_challenges',
            'identity_idempotency_records', 'workspace_memberships', 'account_status_events',
            'account_credentials', 'account_phone_numbers', 'account_email_addresses', 'user_accounts',
            'workspaces', 'qmdb_scheduled_task_runs',
            ] as $table
        ) {
            $this->connection->exec('DROP TABLE IF EXISTS ' . $table);
        }
    }

    /** @return array{int, AccountId} */
    public function account(string $status = 'ACTIVE'): array
    {
        $id = AccountId::generate();
        $now = self::timestamp();
        $statement = $this->connection->prepare(
            'INSERT INTO user_accounts (public_id, account_status, preferred_locale, preferred_time_zone, '
            . 'version, created_at, updated_at) VALUES '
            . "(:public_id, :status, 'en', 'UTC', 1, :created_at, :updated_at)",
        );
        $statement->bindValue(':public_id', $id->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':status', $status);
        $statement->bindValue(':created_at', $now);
        $statement->bindValue(':updated_at', $now);
        $statement->execute();

        return [(int) $this->connection->lastInsertId(), $id];
    }

    /** @return array{int, WorkspaceId, TenantContext} */
    public function workspace(string $status = 'ACTIVE'): array
    {
        $id = WorkspaceId::generate();
        $now = self::timestamp();
        $statement = $this->connection->prepare(
            'INSERT INTO workspaces (public_id, workspace_code, name, status_code, version, created_at, updated_at) '
            . 'VALUES (:public_id, :code, :name, :status, 1, :created_at, :updated_at)',
        );
        $statement->bindValue(':public_id', $id->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':code', 'workspace-' . bin2hex(random_bytes(5)));
        $statement->bindValue(':name', 'Synthetic Authorization Workspace');
        $statement->bindValue(':status', $status);
        $statement->bindValue(':created_at', $now);
        $statement->bindValue(':updated_at', $now);
        $statement->execute();
        $internalId = (int) $this->connection->lastInsertId();

        return [$internalId, $id, TenantContext::trusted($internalId, $id)];
    }

    /** @return array{int, UuidV7} */
    public function membership(int $workspaceInternalId, int $accountInternalId, string $status = 'ACTIVE'): array
    {
        $id = UuidV7::generate();
        $now = self::timestamp();
        $statement = $this->connection->prepare(
            'INSERT INTO workspace_memberships '
            . '(public_id, workspace_id, user_account_id, status_code, version, created_at, updated_at) '
            . 'VALUES (:public_id, :workspace_id, :account_id, :status, 1, :created_at, :updated_at)',
        );
        $statement->bindValue(':public_id', $id->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':workspace_id', $workspaceInternalId, PDO::PARAM_INT);
        $statement->bindValue(':account_id', $accountInternalId, PDO::PARAM_INT);
        $statement->bindValue(':status', $status);
        $statement->bindValue(':created_at', $now);
        $statement->bindValue(':updated_at', $now);
        $statement->execute();

        return [(int) $this->connection->lastInsertId(), $id];
    }

    public function platformAssignment(
        int $accountInternalId,
        string $roleCode,
        ?int $actorAccountInternalId = null,
    ): PlatformRoleAssignmentId {
        $id = PlatformRoleAssignmentId::generate();
        $this->insertPlatformAssignment($id, $accountInternalId, $roleCode, $actorAccountInternalId);

        return $id;
    }

    public function workspaceAssignment(
        int $workspaceInternalId,
        int $membershipInternalId,
        string $roleCode,
        int $actorAccountInternalId,
    ): WorkspaceRoleAssignmentId {
        $id = WorkspaceRoleAssignmentId::generate();
        $statement = $this->connection->prepare(
            'INSERT INTO workspace_role_assignments '
            . '(public_id, workspace_id, membership_id, role_id, role_scope_type, status, version, '
            . 'assigned_by_kind, assigned_by_account_id, assignment_reason_code, assigned_at, correlation_id, '
            . 'created_at, updated_at) SELECT :public_id, :workspace_id, :membership_id, id, '
            . "'WORKSPACE', 'ACTIVE', 1, 'ACCOUNT', :actor_id, 'SECURITY_ADMINISTRATION', :assigned_at, "
            . ':correlation_id, :created_at, :updated_at FROM authorization_roles WHERE code = :role_code',
        );
        $statement->bindValue(':public_id', $id->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':workspace_id', $workspaceInternalId, PDO::PARAM_INT);
        $statement->bindValue(':membership_id', $membershipInternalId, PDO::PARAM_INT);
        $statement->bindValue(':actor_id', $actorAccountInternalId, PDO::PARAM_INT);
        $statement->bindValue(':correlation_id', random_bytes(16), PDO::PARAM_LOB);
        foreach ([':assigned_at', ':created_at', ':updated_at'] as $parameter) {
            $statement->bindValue($parameter, self::timestamp());
        }
        $statement->bindValue(':role_code', $roleCode);
        $statement->execute();

        return $id;
    }

    public function duplicatePermission(string $mode): void
    {
        $publicId = $mode === 'public_id'
            ? '(SELECT public_id FROM authorization_permissions LIMIT 1)'
            : 'UUID_TO_BIN(UUID())';
        $code = $mode === 'code'
            ? "'platform.authorization.view'"
            : "'platform.synthetic.permission'";
        $this->connection->exec('INSERT INTO authorization_permissions '
            . '(public_id, code, scope_type, required_assurance_level, status, owning_module, version, '
            . 'created_at, updated_at) VALUES (' . $publicId . ', ' . $code
            . ", 'PLATFORM', 'PRIMARY', 'ACTIVE', 'security.authorization', 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))");
    }

    public function invalidPermission(string $scope, string $assurance, string $status, int $version): void
    {
        $statement = $this->connection->prepare('INSERT INTO authorization_permissions '
            . '(public_id, code, scope_type, required_assurance_level, status, owning_module, version, '
            . 'created_at, updated_at, retired_at) VALUES (UUID_TO_BIN(UUID()), :code, :scope, :assurance, '
            . ":status, 'security.authorization', :version, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), NULL)");
        $statement->execute([
            ':code' => 'platform.invalid.' . bin2hex(random_bytes(4)),
            ':scope' => $scope,
            ':assurance' => $assurance,
            ':status' => $status,
            ':version' => $version,
        ]);
    }

    public function invalidRole(string $scope, string $status, int $version): void
    {
        $statement = $this->connection->prepare('INSERT INTO authorization_roles '
            . '(public_id, code, scope_type, status, is_system, version, created_at, updated_at, retired_at) '
            . 'VALUES (UUID_TO_BIN(UUID()), :code, :scope, :status, 1, :version, '
            . 'UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), NULL)');
        $statement->execute([
            ':code' => 'platform.invalid_' . bin2hex(random_bytes(4)),
            ':scope' => $scope,
            ':status' => $status,
            ':version' => $version,
        ]);
    }

    public function mapRoles(string $roleCode, string $permissionCode): void
    {
        $statement = $this->connection->prepare('INSERT INTO authorization_role_permissions '
            . '(role_id, role_scope_type, permission_id, permission_scope_type, created_at) '
            . 'SELECT role_definition.id, role_definition.scope_type, permission_definition.id, '
            . 'permission_definition.scope_type, UTC_TIMESTAMP(6) FROM authorization_roles role_definition '
            . 'INNER JOIN authorization_permissions permission_definition ON permission_definition.code = :permission '
            . 'WHERE role_definition.code = :role');
        $statement->execute([':permission' => $permissionCode, ':role' => $roleCode]);
    }

    public function crossWorkspaceAssignment(
        int $workspaceInternalId,
        int $membershipInternalId,
        int $actorAccountInternalId,
    ): void {
        $this->workspaceAssignment(
            $workspaceInternalId,
            $membershipInternalId,
            'workspace.viewer',
            $actorAccountInternalId,
        );
    }

    public function updateAccountStatus(int $internalId, string $status): void
    {
        $this->update('user_accounts', 'account_status', $status, $internalId);
    }

    public function updateWorkspaceStatus(int $internalId, string $status): void
    {
        $this->update('workspaces', 'status_code', $status, $internalId);
    }

    public function updateMembershipStatus(int $internalId, string $status): void
    {
        $this->update('workspace_memberships', 'status_code', $status, $internalId);
    }

    public function updatePermissionStatus(string $code, string $status): void
    {
        $statement = $this->connection->prepare('UPDATE authorization_permissions SET status = :status, '
            . "retired_at = IF(:retired_status = 'RETIRED', UTC_TIMESTAMP(6), NULL) WHERE code = :code");
        $statement->execute([':status' => $status, ':retired_status' => $status, ':code' => $code]);
    }

    public function updateRoleStatus(string $code, string $status): void
    {
        $statement = $this->connection->prepare('UPDATE authorization_roles SET status = :status, '
            . "retired_at = IF(:retired_status = 'RETIRED', UTC_TIMESTAMP(6), NULL) WHERE code = :code");
        $statement->execute([':status' => $status, ':retired_status' => $status, ':code' => $code]);
    }

    public function revokePlatformAssignment(PlatformRoleAssignmentId $id): void
    {
        $statement = $this->connection->prepare("UPDATE platform_role_assignments SET status = 'REVOKED', "
            . "version = version + 1, revoked_by_kind = 'SYSTEM', revocation_reason_code = 'SECURITY_RESPONSE', "
            . 'revoked_at = UTC_TIMESTAMP(6), updated_at = UTC_TIMESTAMP(6) WHERE public_id = :public_id');
        $statement->bindValue(':public_id', $id->toBinary(), PDO::PARAM_LOB);
        $statement->execute();
    }

    public function revokeWorkspaceAssignment(WorkspaceRoleAssignmentId $id): void
    {
        $statement = $this->connection->prepare("UPDATE workspace_role_assignments SET status = 'REVOKED', "
            . "version = version + 1, revoked_by_kind = 'SYSTEM', revocation_reason_code = 'SECURITY_RESPONSE', "
            . 'revoked_at = UTC_TIMESTAMP(6), updated_at = UTC_TIMESTAMP(6) WHERE public_id = :public_id');
        $statement->bindValue(':public_id', $id->toBinary(), PDO::PARAM_LOB);
        $statement->execute();
    }

    /** @return array<string, string> */
    public function tableEngines(): array
    {
        $statement = $this->connection->query("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES "
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'authorization_%' "
            . 'OR TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN '
            . "('platform_role_assignments','workspace_role_assignments') "
            . 'ORDER BY TABLE_NAME');
        if ($statement === false) {
            throw new \RuntimeException('Authorization fixture table engines are unavailable.');
        }
        $engines = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            if (
                !is_array($row)
                || !is_string($row['TABLE_NAME'] ?? null)
                || !is_string($row['ENGINE'] ?? null)
            ) {
                throw new \RuntimeException('Authorization fixture table engine row is invalid.');
            }
            $engines[$row['TABLE_NAME']] = $row['ENGINE'];
        }

        return $engines;
    }

    public function tableCount(string $table): int
    {
        if (
            !in_array($table, [
            'authorization_permissions', 'authorization_roles', 'authorization_role_permissions',
            'platform_role_assignments', 'workspace_role_assignments',
            ], true)
        ) {
            throw new \InvalidArgumentException('Unsupported authorization fixture table.');
        }

        $statement = $this->connection->query('SELECT COUNT(*) FROM ' . $table);
        if ($statement === false) {
            throw new \RuntimeException('Authorization fixture table count is unavailable.');
        }

        return self::integer($statement->fetchColumn());
    }

    public function tableDefinition(string $table): string
    {
        if (!array_key_exists($table, $this->tableEngines())) {
            throw new \InvalidArgumentException('Unsupported authorization fixture table.');
        }
        $statement = $this->connection->query('SHOW CREATE TABLE ' . $table);
        if ($statement === false) {
            throw new \RuntimeException('Authorization fixture table definition is unavailable.');
        }
        $row = $statement->fetch(PDO::FETCH_NUM);
        if (!is_array($row) || !is_string($row[1] ?? null)) {
            throw new \RuntimeException('Authorization fixture table definition is unavailable.');
        }

        return $row[1];
    }

    public function activePlatformAssignmentCount(int $accountInternalId, string $roleCode): int
    {
        $statement = $this->connection->prepare('SELECT COUNT(*) FROM platform_role_assignments assignment '
            . 'INNER JOIN authorization_roles role_definition ON role_definition.id = assignment.role_id '
            . 'WHERE assignment.account_id = :account_id AND role_definition.code = :role '
            . "AND assignment.status = 'ACTIVE'",);
        $statement->execute([':account_id' => $accountInternalId, ':role' => $roleCode]);

        return (int) $statement->fetchColumn();
    }

    public function activeWorkspaceAssignmentCount(int $workspaceInternalId, int $membershipInternalId): int
    {
        $statement = $this->connection->prepare('SELECT COUNT(*) FROM workspace_role_assignments '
            . "WHERE workspace_id = :workspace_id AND membership_id = :membership_id AND status = 'ACTIVE'");
        $statement->execute([
            ':workspace_id' => $workspaceInternalId,
            ':membership_id' => $membershipInternalId,
        ]);

        return (int) $statement->fetchColumn();
    }

    public function roleInternalId(string $roleCode): int
    {
        $statement = $this->connection->prepare('SELECT id FROM authorization_roles WHERE code = :code');
        $statement->execute([':code' => $roleCode]);

        return (int) $statement->fetchColumn();
    }

    public function platformAssignmentInternalId(PlatformRoleAssignmentId $id): int
    {
        return $this->internalId('platform_role_assignments', $id->toBinary());
    }

    public function workspaceAssignmentInternalId(WorkspaceRoleAssignmentId $id): int
    {
        return $this->internalId('workspace_role_assignments', $id->toBinary());
    }

    public function activePlatformSecurityAdministratorCount(): int
    {
        return $this->activeAssignmentCount(
            'platform_role_assignments',
            'platform.security_administrator',
            null,
        );
    }

    public function activeWorkspaceOwnerCount(int $workspaceInternalId): int
    {
        return $this->activeAssignmentCount(
            'workspace_role_assignments',
            'workspace.owner',
            $workspaceInternalId,
        );
    }

    public function stepUpGrant(int $accountInternalId, string $action): int
    {
        $now = self::timestamp();
        $device = $this->connection->prepare(
            'INSERT INTO user_devices (public_id, account_id, token_hash, status, version, created_at, '
            . "last_seen_at, updated_at) VALUES (:public_id, :account_id, :token_hash, 'ACTIVE', 1, "
            . ':created_at, :last_seen_at, :updated_at)',
        );
        $device->bindValue(':public_id', UuidV7::generate()->toBinary(), PDO::PARAM_LOB);
        $device->bindValue(':account_id', $accountInternalId, PDO::PARAM_INT);
        $device->bindValue(':token_hash', random_bytes(32), PDO::PARAM_LOB);
        foreach ([':created_at', ':last_seen_at', ':updated_at'] as $parameter) {
            $device->bindValue($parameter, $now);
        }
        $device->execute();
        $deviceId = (int) $this->connection->lastInsertId();

        $session = $this->connection->prepare(
            'INSERT INTO user_sessions (public_id, account_id, device_id, login_submission_id, '
            . 'current_token_hash, status, version, issued_at, authenticated_at, primary_authentication_method, '
            . 'secondary_authentication_method, assurance_level, strong_authenticated_at, last_seen_at, '
            . 'idle_expires_at, absolute_expires_at, rotated_at, updated_at) VALUES '
            . "(:public_id, :account_id, :device_id, :submission_id, :token_hash, 'ACTIVE', 1, :issued_at, "
            . ":authenticated_at, 'PASSWORD', 'PASSKEY', 'PHISHING_RESISTANT', :strong_authenticated_at, "
            . ':last_seen_at, :idle_expires_at, :absolute_expires_at, :rotated_at, :updated_at)',
        );
        foreach ([':public_id', ':submission_id'] as $parameter) {
            $session->bindValue($parameter, UuidV7::generate()->toBinary(), PDO::PARAM_LOB);
        }
        $session->bindValue(':token_hash', random_bytes(32), PDO::PARAM_LOB);
        $session->bindValue(':account_id', $accountInternalId, PDO::PARAM_INT);
        $session->bindValue(':device_id', $deviceId, PDO::PARAM_INT);
        foreach (
            [':issued_at', ':authenticated_at', ':strong_authenticated_at', ':last_seen_at',
            ':rotated_at', ':updated_at'] as $parameter
        ) {
            $session->bindValue($parameter, $now);
        }
        $session->bindValue(':idle_expires_at', '2026-08-28 11:00:00.000000');
        $session->bindValue(':absolute_expires_at', '2026-08-28 18:00:00.000000');
        $session->execute();
        $sessionId = (int) $this->connection->lastInsertId();

        $grant = $this->connection->prepare('INSERT INTO account_step_up_grants '
            . '(public_id, account_id, session_id, action, assurance_level, status, issued_at, expires_at, '
            . "version, created_at, updated_at) VALUES (:public_id, :account_id, :session_id, :action, "
            . "'PHISHING_RESISTANT', 'ACTIVE', :issued_at, :expires_at, 1, :created_at, :updated_at)");
        $grant->bindValue(':public_id', UuidV7::generate()->toBinary(), PDO::PARAM_LOB);
        $grant->bindValue(':account_id', $accountInternalId, PDO::PARAM_INT);
        $grant->bindValue(':session_id', $sessionId, PDO::PARAM_INT);
        $grant->bindValue(':action', $action);
        foreach ([':issued_at', ':created_at', ':updated_at'] as $parameter) {
            $grant->bindValue($parameter, $now);
        }
        $grant->bindValue(':expires_at', '2026-08-28 10:10:00.000000');
        $grant->execute();

        return $sessionId;
    }

    public function authenticatedStepUpContext(
        int $accountInternalId,
        AccountId $accountId,
        string $action,
    ): AuthenticatedAccountContext {
        $sessionInternalId = $this->stepUpGrant($accountInternalId, $action);
        $statement = $this->connection->prepare('SELECT session.public_id AS session_public_id, '
            . 'session.device_id, device.public_id AS device_public_id FROM user_sessions session '
            . 'INNER JOIN user_devices device ON device.id = session.device_id WHERE session.id = :session_id');
        $statement->execute([':session_id' => $sessionInternalId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (
            !is_array($row) || !is_string($row['session_public_id'] ?? null)
            || !is_string($row['device_public_id'] ?? null)
        ) {
            throw new \RuntimeException('Synthetic authenticated context is unavailable.');
        }
        $now = new DateTimeImmutable('2026-08-28T10:00:00.000000Z');

        return new AuthenticatedAccountContext(
            $accountInternalId,
            $accountId,
            $sessionInternalId,
            SessionId::fromBinary($row['session_public_id']),
            self::integer($row['device_id'] ?? null),
            DeviceId::fromBinary($row['device_public_id']),
            $now,
            1,
            new SessionAuthenticationAssurance(
                AuthenticationMethod::PASSWORD,
                AuthenticationMethod::PASSKEY,
                AuthenticationAssuranceLevel::PHISHING_RESISTANT,
                $now,
                $now,
            ),
        );
    }

    public function verifiedEmail(int $accountInternalId): int
    {
        $statement = $this->connection->prepare('INSERT INTO account_email_addresses '
            . '(public_id, user_account_id, email_ciphertext, encryption_key_id, lookup_hash, status_code, '
            . "verified_at, version, created_at, updated_at) VALUES (:public_id, :account_id, :ciphertext, "
            . ":key_id, :lookup_hash, 'VERIFIED', :verified_at, 1, :created_at, :updated_at)");
        $statement->bindValue(':public_id', UuidV7::generate()->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':account_id', $accountInternalId, PDO::PARAM_INT);
        $statement->bindValue(':ciphertext', random_bytes(48), PDO::PARAM_LOB);
        $statement->bindValue(':key_id', 'test-v1');
        $statement->bindValue(':lookup_hash', random_bytes(32), PDO::PARAM_LOB);
        foreach ([':verified_at', ':created_at', ':updated_at'] as $parameter) {
            $statement->bindValue($parameter, self::timestamp());
        }
        $statement->execute();

        return (int) $this->connection->lastInsertId();
    }

    public function notificationCount(string $type): int
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*) FROM account_security_notifications WHERE notification_type = :type',
        );
        $statement->execute([':type' => $type]);

        return (int) $statement->fetchColumn();
    }

    public function consumedStepUpGrantCount(int $accountInternalId, string $action): int
    {
        $statement = $this->connection->prepare("SELECT COUNT(*) FROM account_step_up_grants WHERE "
            . "account_id = :account_id AND action = :action AND status = 'CONSUMED'");
        $statement->execute([':account_id' => $accountInternalId, ':action' => $action]);

        return (int) $statement->fetchColumn();
    }

    private function insertPlatformAssignment(
        PlatformRoleAssignmentId $id,
        int $accountInternalId,
        string $roleCode,
        ?int $actorAccountInternalId,
    ): void {
        $statement = $this->connection->prepare('INSERT INTO platform_role_assignments '
            . '(public_id, account_id, role_id, role_scope_type, status, version, assigned_by_kind, '
            . 'assigned_by_account_id, assignment_reason_code, assigned_at, correlation_id, created_at, updated_at) '
            . 'SELECT :public_id, :account_id, id, :scope, '
            . "'ACTIVE', 1, :actor_kind, :actor_id, 'SECURITY_ADMINISTRATION', :assigned_at, "
            . ':correlation_id, :created_at, :updated_at FROM authorization_roles WHERE code = :role_code');
        $statement->bindValue(':public_id', $id->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':account_id', $accountInternalId, PDO::PARAM_INT);
        $statement->bindValue(':scope', 'PLATFORM');
        $statement->bindValue(':actor_kind', $actorAccountInternalId === null ? 'SYSTEM' : 'ACCOUNT');
        $statement->bindValue(':actor_id', $actorAccountInternalId, $actorAccountInternalId === null
            ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':correlation_id', random_bytes(16), PDO::PARAM_LOB);
        foreach ([':assigned_at', ':created_at', ':updated_at'] as $parameter) {
            $statement->bindValue($parameter, self::timestamp());
        }
        $statement->bindValue(':role_code', $roleCode);
        $statement->execute();
    }

    private function update(string $table, string $field, string $value, int $id): void
    {
        $allowed = [
            'user_accounts:account_status',
            'workspaces:status_code',
            'workspace_memberships:status_code',
        ];
        if (!in_array($table . ':' . $field, $allowed, true)) {
            throw new \InvalidArgumentException('Unsupported authorization fixture update.');
        }
        $statement = $this->connection->prepare(
            'UPDATE ' . $table . ' SET ' . $field . ' = :value, updated_at = UTC_TIMESTAMP(6) WHERE id = :id',
        );
        $statement->execute([':value' => $value, ':id' => $id]);
    }

    private function internalId(string $table, string $publicId): int
    {
        if (!in_array($table, ['platform_role_assignments', 'workspace_role_assignments'], true)) {
            throw new \InvalidArgumentException('Unsupported authorization assignment table.');
        }
        $statement = $this->connection->prepare('SELECT id FROM ' . $table . ' WHERE public_id = :public_id');
        $statement->bindValue(':public_id', $publicId, PDO::PARAM_LOB);
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    private function activeAssignmentCount(string $table, string $roleCode, ?int $workspaceInternalId): int
    {
        if (!in_array($table, ['platform_role_assignments', 'workspace_role_assignments'], true)) {
            throw new \InvalidArgumentException('Unsupported authorization assignment table.');
        }
        $workspace = $workspaceInternalId === null ? '' : ' AND assignment.workspace_id = :workspace_id';
        $statement = $this->connection->prepare('SELECT COUNT(*) FROM ' . $table . ' assignment '
            . 'INNER JOIN authorization_roles role_definition ON role_definition.id = assignment.role_id '
            . "WHERE role_definition.code = :role AND assignment.status = 'ACTIVE'" . $workspace);
        $statement->bindValue(':role', $roleCode);
        if ($workspaceInternalId !== null) {
            $statement->bindValue(':workspace_id', $workspaceInternalId, PDO::PARAM_INT);
        }
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    private static function timestamp(): string
    {
        return (new DateTimeImmutable('2026-08-28T10:00:00.000000Z'))->format('Y-m-d H:i:s.u');
    }

    private static function integer(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value) || preg_match('/\A[0-9]+\z/D', $value) !== 1) {
            throw new \RuntimeException('Authorization fixture integer is invalid.');
        }

        return (int) $value;
    }
}
