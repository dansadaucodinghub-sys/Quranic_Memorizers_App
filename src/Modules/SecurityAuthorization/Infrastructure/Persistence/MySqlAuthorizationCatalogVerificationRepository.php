<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Infrastructure\Persistence;

use PDO;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationCatalogVerificationReport;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationCatalog;
use Qmdb\Modules\SecurityAuthorization\Domain\Repository\AuthorizationCatalogVerificationRepository;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;

final readonly class MySqlAuthorizationCatalogVerificationRepository implements
    AuthorizationCatalogVerificationRepository
{
    public function __construct(private DatabaseConnectionProvider $provider)
    {
    }

    public function verify(AuthorizationCatalog $catalog): AuthorizationCatalogVerificationReport
    {
        $connection = $this->provider->connection();
        $errors = [];
        if (
            $this->count($connection, "SELECT COUNT(*) FROM qmdb_schema_migrations WHERE status = 'APPLIED' "
            . "AND migration_id IN ('20260826011600_create_authorization_catalog_foundation', "
            . "'20260826011700_create_platform_role_assignment_foundation', "
            . "'20260826011800_create_workspace_role_assignment_foundation')") !== 3
        ) {
            $errors[] = 'AUTHORIZATION_MIGRATIONS_NOT_APPLIED';
        }
        if (
            $this->count($connection, "SELECT COUNT(*) FROM qmdb_schema_seeds WHERE status = 'APPLIED' "
            . "AND seed_id = '20260826020100_seed_foundational_authorization_catalog'") !== 1
        ) {
            $errors[] = 'AUTHORIZATION_SEED_NOT_APPLIED';
        }
        if ($errors !== []) {
            return new AuthorizationCatalogVerificationReport(0, 0, 0, 0, 0, $errors);
        }
        $permissions = $this->permissions($connection);
        $roles = $this->roles($connection);
        $mappings = $this->mappings($connection);
        foreach ($catalog->permissions() as $expected) {
            $actual = $permissions[$expected->code->value()] ?? null;
            if (
                $actual === null || $actual !== [
                $expected->id->toString(),
                $expected->scopeType->value,
                $expected->requiredAssurance->value,
                $expected->status->value,
                $expected->owningModule,
                $expected->version,
                ]
            ) {
                $errors[] = 'AUTHORIZATION_PERMISSION_DRIFT';
            }
        }
        if (count($permissions) !== count($catalog->permissions())) {
            $errors[] = 'AUTHORIZATION_PERMISSION_COUNT_MISMATCH';
        }
        foreach ($catalog->roles() as $expected) {
            $actual = $roles[$expected->code->value()] ?? null;
            if (
                $actual === null || $actual !== [
                $expected->id->toString(),
                $expected->scopeType->value,
                $expected->status->value,
                $expected->isSystem ? 1 : 0,
                $expected->version,
                ]
            ) {
                $errors[] = 'AUTHORIZATION_ROLE_DRIFT';
            }
        }
        if (count($roles) !== count($catalog->roles())) {
            $errors[] = 'AUTHORIZATION_ROLE_COUNT_MISMATCH';
        }
        $expectedMappings = [];
        foreach ($catalog->mappings() as $mapping) {
            $expectedMappings[] = $mapping->roleCode->value() . "\0" . $mapping->permissionCode->value()
                . "\0" . $mapping->scopeType->value;
        }
        sort($expectedMappings, SORT_STRING);
        if ($mappings !== $expectedMappings) {
            $errors[] = 'AUTHORIZATION_MAPPING_DRIFT';
        }
        if (
            $this->count($connection, 'SELECT COUNT(*) FROM authorization_role_permissions mapping '
            . 'LEFT JOIN authorization_roles role_definition ON role_definition.id = mapping.role_id '
            . 'LEFT JOIN authorization_permissions permission_definition '
            . 'ON permission_definition.id = mapping.permission_id '
            . 'WHERE role_definition.id IS NULL OR permission_definition.id IS NULL') !== 0
        ) {
            $errors[] = 'AUTHORIZATION_ORPHAN_MAPPING';
        }
        foreach (['platform_role_assignments', 'workspace_role_assignments'] as $table) {
            $scope = $table === 'platform_role_assignments' ? '' : 'workspace_id, ';
            if (
                $this->count($connection, 'SELECT COUNT(*) FROM (SELECT ' . $scope
                . 'account_key, role_id, COUNT(*) duplicate_count FROM (SELECT '
                . ($table === 'platform_role_assignments'
                    ? 'account_id AS account_key, role_id, status'
                    : 'workspace_id, membership_id AS account_key, role_id, status')
                . ' FROM ' . $table . ") rows_for_check WHERE status = 'ACTIVE' GROUP BY " . $scope
                . 'account_key, role_id HAVING COUNT(*) > 1) duplicates') !== 0
            ) {
                $errors[] = 'AUTHORIZATION_DUPLICATE_ACTIVE_ASSIGNMENT';
            }
        }
        if ($this->protectedPlatformRoleInvalid($connection)) {
            $errors[] = 'AUTHORIZATION_PLATFORM_ADMINISTRATOR_INVARIANT';
        }
        if ($this->protectedWorkspaceRoleInvalid($connection)) {
            $errors[] = 'AUTHORIZATION_WORKSPACE_OWNER_INVARIANT';
        }

        return new AuthorizationCatalogVerificationReport(
            count($permissions),
            count($roles),
            count($mappings),
            $this->count($connection, 'SELECT COUNT(*) FROM platform_role_assignments'),
            $this->count($connection, 'SELECT COUNT(*) FROM workspace_role_assignments'),
            array_values(array_unique($errors)),
        );
    }

    /** @return array<string, array{string, string, string, string, string, int}> */
    private function permissions(PDO $connection): array
    {
        $statement = $connection->prepare(
            'SELECT public_id, code, scope_type, required_assurance_level, status, owning_module, version '
            . 'FROM authorization_permissions ORDER BY code',
        );
        $statement->execute();
        $permissions = [];
        while (($value = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $row = AuthorizationPersistence::row($value);
            if ($row === null) {
                throw new \UnexpectedValueException('Authorization permission verification row is invalid.');
            }
            $permissions[AuthorizationPersistence::string($row, 'code')] = [
                \Qmdb\Modules\SecurityAuthorization\Domain\PermissionId::fromBinary(
                    AuthorizationPersistence::string($row, 'public_id'),
                )->toString(),
                AuthorizationPersistence::string($row, 'scope_type'),
                AuthorizationPersistence::string($row, 'required_assurance_level'),
                AuthorizationPersistence::string($row, 'status'),
                AuthorizationPersistence::string($row, 'owning_module'),
                AuthorizationPersistence::integer($row, 'version'),
            ];
        }

        return $permissions;
    }

    /** @return array<string, array{string, string, string, int, int}> */
    private function roles(PDO $connection): array
    {
        $statement = $connection->prepare(
            'SELECT public_id, code, scope_type, status, is_system, version FROM authorization_roles ORDER BY code',
        );
        $statement->execute();
        $roles = [];
        while (($value = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $row = AuthorizationPersistence::row($value);
            if ($row === null) {
                throw new \UnexpectedValueException('Authorization role verification row is invalid.');
            }
            $roles[AuthorizationPersistence::string($row, 'code')] = [
                \Qmdb\Modules\SecurityAuthorization\Domain\RoleId::fromBinary(
                    AuthorizationPersistence::string($row, 'public_id'),
                )->toString(),
                AuthorizationPersistence::string($row, 'scope_type'),
                AuthorizationPersistence::string($row, 'status'),
                AuthorizationPersistence::integer($row, 'is_system'),
                AuthorizationPersistence::integer($row, 'version'),
            ];
        }

        return $roles;
    }

    /** @return list<string> */
    private function mappings(PDO $connection): array
    {
        $statement = $connection->prepare(
            'SELECT role_definition.code AS role_code, permission_definition.code AS permission_code, '
            . 'mapping.role_scope_type FROM authorization_role_permissions mapping '
            . 'INNER JOIN authorization_roles role_definition ON role_definition.id = mapping.role_id '
            . 'INNER JOIN authorization_permissions permission_definition '
            . 'ON permission_definition.id = mapping.permission_id '
            . 'ORDER BY role_definition.code, permission_definition.code',
        );
        $statement->execute();
        $mappings = [];
        while (($value = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $row = AuthorizationPersistence::row($value);
            if ($row === null) {
                throw new \UnexpectedValueException('Authorization mapping verification row is invalid.');
            }
            $mappings[] = AuthorizationPersistence::string($row, 'role_code') . "\0"
                . AuthorizationPersistence::string($row, 'permission_code') . "\0"
                . AuthorizationPersistence::string($row, 'role_scope_type');
        }
        sort($mappings, SORT_STRING);

        return $mappings;
    }

    private function count(PDO $connection, string $sql): int
    {
        $statement = $connection->prepare($sql);
        $statement->execute();
        $value = $statement->fetchColumn();

        return is_int($value) || is_string($value) && ctype_digit($value) ? (int) $value : 0;
    }

    private function protectedPlatformRoleInvalid(PDO $connection): bool
    {
        $total = $this->count($connection, 'SELECT COUNT(*) FROM platform_role_assignments assignment '
            . 'INNER JOIN authorization_roles role_definition ON role_definition.id = assignment.role_id '
            . "AND role_definition.code = 'platform.security_administrator' WHERE assignment.status = 'ACTIVE'");
        $usable = $this->count($connection, 'SELECT COUNT(*) FROM platform_role_assignments assignment '
            . 'INNER JOIN authorization_roles role_definition ON role_definition.id = assignment.role_id '
            . "AND role_definition.code = 'platform.security_administrator' AND role_definition.status = 'ACTIVE' "
            . 'INNER JOIN user_accounts account ON account.id = assignment.account_id '
            . "AND account.account_status = 'ACTIVE' WHERE assignment.status = 'ACTIVE'");

        return $total > 0 && $usable === 0;
    }

    private function protectedWorkspaceRoleInvalid(PDO $connection): bool
    {
        return $this->count($connection, 'SELECT COUNT(*) FROM (SELECT assignment.workspace_id '
            . 'FROM workspace_role_assignments assignment '
            . 'INNER JOIN authorization_roles role_definition ON role_definition.id = assignment.role_id '
            . "AND role_definition.code = 'workspace.owner' WHERE assignment.status = 'ACTIVE' "
            . 'GROUP BY assignment.workspace_id HAVING SUM(role_definition.status = \'ACTIVE\' '
            . 'AND EXISTS (SELECT 1 FROM workspace_memberships membership '
            . 'INNER JOIN user_accounts account ON account.id = membership.user_account_id '
            . "WHERE membership.id = assignment.membership_id AND membership.workspace_id = assignment.workspace_id "
            . "AND membership.status_code = 'ACTIVE' AND account.account_status = 'ACTIVE')) = 0) invalid") > 0;
    }
}
