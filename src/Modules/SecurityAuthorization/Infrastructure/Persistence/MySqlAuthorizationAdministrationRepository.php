<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Infrastructure\Persistence;

use PDO;
use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationAccountRecord;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationMembershipRecord;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationRoleRecord;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationScopeType;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\Repository\AuthorizationAdministrationRepository;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleCode;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleId;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleStatus;
use Qmdb\Modules\Tenancy\Application\TenantContext;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class MySqlAuthorizationAdministrationRepository implements AuthorizationAdministrationRepository
{
    public function __construct(private DatabaseConnectionProvider $provider)
    {
    }

    public function account(AccountId $id): ?AuthorizationAccountRecord
    {
        $statement = $this->provider->connection()->prepare(
            'SELECT id, public_id, account_status FROM user_accounts WHERE public_id = :public_id LIMIT 1',
        );
        $statement->bindValue(':public_id', $id->toBinary(), PDO::PARAM_LOB);
        $statement->execute();
        $row = AuthorizationPersistence::row($statement->fetch(PDO::FETCH_ASSOC));
        if ($row === null) {
            return null;
        }

        return new AuthorizationAccountRecord(
            AuthorizationPersistence::integer($row, 'id'),
            AccountId::fromBinary(AuthorizationPersistence::string($row, 'public_id')),
            AuthorizationPersistence::string($row, 'account_status') === 'ACTIVE',
        );
    }

    public function role(RoleCode $code): ?AuthorizationRoleRecord
    {
        $statement = $this->provider->connection()->prepare(
            'SELECT id, public_id, code, scope_type, status FROM authorization_roles WHERE code = :code LIMIT 1',
        );
        $statement->execute([':code' => $code->value()]);
        $row = AuthorizationPersistence::row($statement->fetch(PDO::FETCH_ASSOC));
        if ($row === null) {
            return null;
        }

        return new AuthorizationRoleRecord(
            AuthorizationPersistence::integer($row, 'id'),
            RoleId::fromBinary(AuthorizationPersistence::string($row, 'public_id')),
            new RoleCode(AuthorizationPersistence::string($row, 'code')),
            AuthorizationScopeType::from(AuthorizationPersistence::string($row, 'scope_type')),
            RoleStatus::from(AuthorizationPersistence::string($row, 'status')),
        );
    }

    public function membership(TenantContext $context, UuidV7 $id): ?AuthorizationMembershipRecord
    {
        $statement = $this->provider->connection()->prepare(
            'SELECT membership.id, membership.public_id, membership.workspace_id, '
            . 'membership.user_account_id, membership.status_code, account.public_id AS account_public_id, '
            . 'account.account_status FROM workspace_memberships membership '
            . 'INNER JOIN user_accounts account ON account.id = membership.user_account_id '
            . 'WHERE membership.workspace_id = :workspace_id AND membership.public_id = :public_id LIMIT 1',
        );
        $statement->bindValue(':workspace_id', $context->workspaceInternalId(), PDO::PARAM_INT);
        $statement->bindValue(':public_id', $id->toBinary(), PDO::PARAM_LOB);
        $statement->execute();
        $row = AuthorizationPersistence::row($statement->fetch(PDO::FETCH_ASSOC));
        if ($row === null) {
            return null;
        }

        return new AuthorizationMembershipRecord(
            AuthorizationPersistence::integer($row, 'id'),
            UuidV7::fromBinary(AuthorizationPersistence::string($row, 'public_id')),
            AuthorizationPersistence::integer($row, 'workspace_id'),
            AuthorizationPersistence::integer($row, 'user_account_id'),
            AccountId::fromBinary(AuthorizationPersistence::string($row, 'account_public_id')),
            AuthorizationPersistence::string($row, 'status_code') === 'ACTIVE'
                && AuthorizationPersistence::string($row, 'account_status') === 'ACTIVE',
        );
    }

    public function activeMembershipForAccount(
        TenantContext $context,
        int $accountInternalId,
    ): ?AuthorizationMembershipRecord {
        $statement = $this->provider->connection()->prepare(
            'SELECT membership.id, membership.public_id, membership.workspace_id, '
            . 'membership.user_account_id, membership.status_code, account.public_id AS account_public_id, '
            . 'account.account_status FROM workspace_memberships membership '
            . 'INNER JOIN user_accounts account ON account.id = membership.user_account_id '
            . 'WHERE membership.workspace_id = :workspace_id AND membership.user_account_id = :account_id '
            . "AND membership.status_code = 'ACTIVE' AND account.account_status = 'ACTIVE' LIMIT 1",
        );
        $statement->execute([
            ':workspace_id' => $context->workspaceInternalId(),
            ':account_id' => $accountInternalId,
        ]);
        $row = AuthorizationPersistence::row($statement->fetch(PDO::FETCH_ASSOC));
        if ($row === null) {
            return null;
        }

        return new AuthorizationMembershipRecord(
            AuthorizationPersistence::integer($row, 'id'),
            UuidV7::fromBinary(AuthorizationPersistence::string($row, 'public_id')),
            AuthorizationPersistence::integer($row, 'workspace_id'),
            AuthorizationPersistence::integer($row, 'user_account_id'),
            AccountId::fromBinary(AuthorizationPersistence::string($row, 'account_public_id')),
            true,
        );
    }

    public function activePermissionsForRole(AuthorizationRoleRecord $role, int $limit = 100): array
    {
        $statement = $this->provider->connection()->prepare(
            'SELECT permission_definition.code FROM authorization_role_permissions mapping '
            . 'INNER JOIN authorization_permissions permission_definition '
            . 'ON permission_definition.id = mapping.permission_id '
            . "AND permission_definition.status = 'ACTIVE' "
            . 'WHERE mapping.role_id = :role_id AND mapping.role_scope_type = :scope_type '
            . 'ORDER BY permission_definition.code LIMIT :row_limit',
        );
        $statement->bindValue(':role_id', $role->internalId, PDO::PARAM_INT);
        $statement->bindValue(':scope_type', $role->scopeType->value);
        $statement->bindValue(':row_limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $statement->execute();
        $permissions = [];
        while (($value = $statement->fetchColumn()) !== false) {
            if (!is_string($value)) {
                throw new \UnexpectedValueException('Role permission row is invalid.');
            }
            $permissions[] = new PermissionCode($value);
        }

        return $permissions;
    }
}
