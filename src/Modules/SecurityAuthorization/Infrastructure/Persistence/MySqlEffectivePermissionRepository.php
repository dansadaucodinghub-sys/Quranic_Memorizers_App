<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Infrastructure\Persistence;

use PDO;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationScopeType;
use Qmdb\Modules\SecurityAuthorization\Domain\EffectivePermissionEvidence;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionId;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionStatus;
use Qmdb\Modules\SecurityAuthorization\Domain\PersistedPermission;
use Qmdb\Modules\SecurityAuthorization\Domain\Repository\EffectivePermissionRepository;
use Qmdb\Modules\TenancyContext\Domain\AccountWorkspaceTenantContext;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;

final readonly class MySqlEffectivePermissionRepository implements EffectivePermissionRepository
{
    public function __construct(private DatabaseConnectionProvider $provider)
    {
    }

    public function permission(PermissionCode $code): ?PersistedPermission
    {
        $statement = $this->provider->connection()->prepare(
            'SELECT id, public_id, code, scope_type, required_assurance_level, status '
            . 'FROM authorization_permissions WHERE code = :code LIMIT 1',
        );
        $statement->execute([':code' => $code->value()]);
        $row = AuthorizationPersistence::row($statement->fetch(PDO::FETCH_ASSOC));
        if ($row === null) {
            return null;
        }

        return new PersistedPermission(
            AuthorizationPersistence::integer($row, 'id'),
            PermissionId::fromBinary(AuthorizationPersistence::string($row, 'public_id')),
            new PermissionCode(AuthorizationPersistence::string($row, 'code')),
            AuthorizationScopeType::from(AuthorizationPersistence::string($row, 'scope_type')),
            AuthenticationAssuranceLevel::from(AuthorizationPersistence::string($row, 'required_assurance_level')),
            PermissionStatus::from(AuthorizationPersistence::string($row, 'status')),
        );
    }

    public function accountIsActive(int $accountInternalId): bool
    {
        return $this->exists(
            "SELECT 1 FROM user_accounts WHERE id = :account_id AND account_status = 'ACTIVE' LIMIT 1",
            [':account_id' => $accountInternalId],
        );
    }

    public function workspaceIsActive(AccountWorkspaceTenantContext $context): bool
    {
        return $this->exists(
            "SELECT 1 FROM workspaces WHERE id = :workspace_id AND status_code = 'ACTIVE' LIMIT 1",
            [':workspace_id' => $context->workspaceInternalId],
        );
    }

    public function membershipIsActive(AccountWorkspaceTenantContext $context): bool
    {
        return $this->exists(
            "SELECT 1 FROM workspace_memberships WHERE id = :membership_id AND workspace_id = :workspace_id "
            . "AND user_account_id = :account_id AND status_code = 'ACTIVE' LIMIT 1",
            [
                ':membership_id' => $context->membership->membershipInternalId,
                ':workspace_id' => $context->workspaceInternalId,
                ':account_id' => $context->accountInternalId,
            ],
        );
    }

    public function findEffectivePlatformPermission(
        int $accountInternalId,
        PermissionCode $permission,
    ): EffectivePermissionEvidence {
        $parameters = [':account_id' => $accountInternalId];
        $assignment = $this->exists(
            "SELECT 1 FROM platform_role_assignments WHERE account_id = :account_id AND status = 'ACTIVE' LIMIT 1",
            $parameters,
        );
        $role = $this->exists(
            'SELECT 1 FROM platform_role_assignments assignment '
            . 'INNER JOIN authorization_roles role_definition ON role_definition.id = assignment.role_id '
            . "AND role_definition.scope_type = 'PLATFORM' AND role_definition.status = 'ACTIVE' "
            . "WHERE assignment.account_id = :account_id AND assignment.status = 'ACTIVE' LIMIT 1",
            $parameters,
        );
        $mapped = $this->exists(
            'SELECT 1 FROM platform_role_assignments assignment '
            . 'INNER JOIN authorization_roles role_definition ON role_definition.id = assignment.role_id '
            . "AND role_definition.scope_type = 'PLATFORM' AND role_definition.status = 'ACTIVE' "
            . 'INNER JOIN authorization_role_permissions mapping ON mapping.role_id = role_definition.id '
            . 'AND mapping.role_scope_type = role_definition.scope_type '
            . 'INNER JOIN authorization_permissions permission_definition '
            . 'ON permission_definition.id = mapping.permission_id '
            . "AND permission_definition.scope_type = 'PLATFORM' AND permission_definition.status = 'ACTIVE' "
            . "WHERE assignment.account_id = :account_id AND assignment.status = 'ACTIVE' "
            . 'AND permission_definition.code = :permission_code LIMIT 1',
            [...$parameters, ':permission_code' => $permission->value()],
        );

        return new EffectivePermissionEvidence($assignment, $role, $mapped);
    }

    public function findEffectiveWorkspacePermission(
        AccountWorkspaceTenantContext $context,
        PermissionCode $permission,
    ): EffectivePermissionEvidence {
        $parameters = [
            ':account_id' => $context->accountInternalId,
            ':workspace_id' => $context->workspaceInternalId,
            ':membership_id' => $context->membership->membershipInternalId,
        ];
        $membershipJoin = ' FROM workspace_role_assignments assignment '
            . 'INNER JOIN workspace_memberships membership ON membership.id = assignment.membership_id '
            . 'AND membership.workspace_id = assignment.workspace_id '
            . "AND membership.status_code = 'ACTIVE' ";
        $where = "WHERE assignment.workspace_id = :workspace_id AND assignment.membership_id = :membership_id "
            . "AND membership.user_account_id = :account_id "
            . "AND assignment.status = 'ACTIVE' ";
        $assignment = $this->exists('SELECT 1' . $membershipJoin . $where . 'LIMIT 1', $parameters);
        $role = $this->exists(
            'SELECT 1' . $membershipJoin
            . 'INNER JOIN authorization_roles role_definition ON role_definition.id = assignment.role_id '
            . "AND role_definition.scope_type = 'WORKSPACE' AND role_definition.status = 'ACTIVE' "
            . $where . 'LIMIT 1',
            $parameters,
        );
        $mapped = $this->exists(
            'SELECT 1' . $membershipJoin
            . 'INNER JOIN authorization_roles role_definition ON role_definition.id = assignment.role_id '
            . "AND role_definition.scope_type = 'WORKSPACE' AND role_definition.status = 'ACTIVE' "
            . 'INNER JOIN authorization_role_permissions mapping ON mapping.role_id = role_definition.id '
            . 'AND mapping.role_scope_type = role_definition.scope_type '
            . 'INNER JOIN authorization_permissions permission_definition '
            . 'ON permission_definition.id = mapping.permission_id '
            . "AND permission_definition.scope_type = 'WORKSPACE' AND permission_definition.status = 'ACTIVE' "
            . $where . 'AND permission_definition.code = :permission_code LIMIT 1',
            [...$parameters, ':permission_code' => $permission->value()],
        );

        return new EffectivePermissionEvidence($assignment, $role, $mapped);
    }

    public function listEffectivePlatformPermissions(
        int $accountInternalId,
        int $limit = 100,
        bool $forUpdate = false,
    ): array {
        return $this->permissionList(
            'SELECT ' . ($forUpdate ? '' : 'DISTINCT ') . 'permission_definition.code '
            . 'FROM platform_role_assignments assignment '
            . 'INNER JOIN authorization_roles role_definition ON role_definition.id = assignment.role_id '
            . "AND role_definition.scope_type = 'PLATFORM' AND role_definition.status = 'ACTIVE' "
            . 'INNER JOIN authorization_role_permissions mapping ON mapping.role_id = role_definition.id '
            . 'INNER JOIN authorization_permissions permission_definition '
            . 'ON permission_definition.id = mapping.permission_id '
            . "AND permission_definition.scope_type = 'PLATFORM' AND permission_definition.status = 'ACTIVE' "
            . "WHERE assignment.account_id = :account_id AND assignment.status = 'ACTIVE' "
            . 'ORDER BY permission_definition.code LIMIT :row_limit' . ($forUpdate ? ' FOR UPDATE' : ''),
            [':account_id' => $accountInternalId],
            $limit,
        );
    }

    public function listEffectiveWorkspacePermissions(
        AccountWorkspaceTenantContext $context,
        int $limit = 100,
        bool $forUpdate = false,
    ): array {
        return $this->permissionList(
            'SELECT ' . ($forUpdate ? '' : 'DISTINCT ') . 'permission_definition.code '
            . 'FROM workspace_role_assignments assignment '
            . 'INNER JOIN workspace_memberships membership ON membership.id = assignment.membership_id '
            . 'AND membership.workspace_id = assignment.workspace_id '
            . "AND membership.status_code = 'ACTIVE' "
            . 'INNER JOIN authorization_roles role_definition ON role_definition.id = assignment.role_id '
            . "AND role_definition.scope_type = 'WORKSPACE' AND role_definition.status = 'ACTIVE' "
            . 'INNER JOIN authorization_role_permissions mapping ON mapping.role_id = role_definition.id '
            . 'INNER JOIN authorization_permissions permission_definition '
            . 'ON permission_definition.id = mapping.permission_id '
            . "AND permission_definition.scope_type = 'WORKSPACE' AND permission_definition.status = 'ACTIVE' "
            . 'WHERE assignment.workspace_id = :workspace_id AND assignment.membership_id = :membership_id '
            . 'AND membership.user_account_id = :account_id '
            . "AND assignment.status = 'ACTIVE' ORDER BY permission_definition.code LIMIT :row_limit"
            . ($forUpdate ? ' FOR UPDATE' : ''),
            [
                ':workspace_id' => $context->workspaceInternalId,
                ':membership_id' => $context->membership->membershipInternalId,
                ':account_id' => $context->accountInternalId,
            ],
            $limit,
        );
    }

    /** @param array<string, int|string> $parameters */
    private function exists(string $sql, array $parameters): bool
    {
        $statement = $this->provider->connection()->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchColumn() !== false;
    }

    /**
     * @param array<string, int|string> $parameters
     * @return list<PermissionCode>
     */
    private function permissionList(string $sql, array $parameters, int $limit): array
    {
        $statement = $this->provider->connection()->prepare($sql);
        foreach ($parameters as $name => $value) {
            $statement->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->bindValue(':row_limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $statement->execute();
        $permissions = [];
        while (($value = $statement->fetchColumn()) !== false) {
            if (!is_string($value)) {
                throw new \UnexpectedValueException('Effective permission row is invalid.');
            }
            $permissions[$value] = new PermissionCode($value);
        }

        return array_values($permissions);
    }
}
