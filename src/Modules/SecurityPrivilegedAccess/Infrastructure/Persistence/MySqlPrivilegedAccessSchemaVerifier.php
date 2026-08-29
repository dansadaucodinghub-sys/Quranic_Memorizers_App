<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace Qmdb\Modules\SecurityPrivilegedAccess\Infrastructure\Persistence;

use PDO;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessSchemaVerifier;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessVerificationReport;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessPermissionPolicyCatalog;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessAuthorizationCatalog;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;

final readonly class MySqlPrivilegedAccessSchemaVerifier implements PrivilegedAccessSchemaVerifier
{
    public function __construct(private DatabaseConnectionProvider $provider)
    {
    }

    public function verify(): PrivilegedAccessVerificationReport
    {
        $pdo = $this->provider->connection();
        $errors = [];
        if (
            $this->count($pdo, "SELECT COUNT(*) FROM qmdb_schema_migrations WHERE status = 'APPLIED' AND migration_id IN ("
            . "'20260826012000_extend_privileged_access_security_catalog',"
            . "'20260826012100_create_privileged_access_request_foundation',"
            . "'20260826012200_create_privileged_access_activation_foundation')") !== 3
        ) {
            $errors[] = 'PRIVILEGED_ACCESS_MIGRATIONS_NOT_APPLIED';
        }
        if (
            $this->count($pdo, "SELECT COUNT(*) FROM qmdb_schema_seeds WHERE status = 'APPLIED' "
            . "AND seed_id = '20260826020200_seed_privileged_access_catalog'") !== 1
        ) {
            $errors[] = 'PRIVILEGED_ACCESS_SEED_NOT_APPLIED';
        }
        $tables = ['privileged_access_permission_policies', 'privileged_access_requests',
            'privileged_access_request_permissions', 'privileged_access_approvals',
            'privileged_access_activations', 'privileged_access_events', 'privileged_access_reviews'];
        $names = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() "
            . "AND table_name IN ('" . implode("','", $tables) . "') AND engine = 'InnoDB'");
        if ($names === false || count($names->fetchAll(PDO::FETCH_COLUMN)) !== count($tables)) {
            $errors[] = 'PRIVILEGED_ACCESS_TABLES_INVALID';
        }
        $policyCount = $this->count($pdo, 'SELECT COUNT(*) FROM privileged_access_permission_policies');
        if ($policyCount !== count(PrivilegedAccessPermissionPolicyCatalog::entries())) {
            $errors[] = 'PRIVILEGED_ACCESS_POLICY_DRIFT';
        }
        if (
            $this->count($pdo, "SELECT COUNT(*) FROM authorization_permissions WHERE owning_module = 'security.privileged_access'")
            !== count(PrivilegedAccessAuthorizationCatalog::permissions())
        ) {
            $errors[] = 'PRIVILEGED_ACCESS_PERMISSION_CATALOG_DRIFT';
        }
        if (
            $this->count($pdo, "SELECT COUNT(*) FROM authorization_roles WHERE code IN "
            . "('platform.privileged_access_administrator', 'platform.support_operator')")
            !== count(PrivilegedAccessAuthorizationCatalog::roles())
        ) {
            $errors[] = 'PRIVILEGED_ACCESS_ROLE_CATALOG_DRIFT';
        }
        $mappingCount = array_sum(array_map('count', PrivilegedAccessAuthorizationCatalog::mappings()));
        if (
            $this->count($pdo, "SELECT COUNT(*) FROM authorization_role_permissions mapping "
            . "INNER JOIN authorization_permissions permission_definition ON permission_definition.id = mapping.permission_id "
            . "WHERE permission_definition.owning_module = 'security.privileged_access'") !== $mappingCount
        ) {
            $errors[] = 'PRIVILEGED_ACCESS_ROLE_MAPPING_DRIFT';
        }
        if (
            $this->count($pdo, "SELECT COUNT(*) FROM privileged_access_permission_policies policy "
            . "INNER JOIN authorization_permissions permission_definition ON permission_definition.id = policy.permission_id "
            . "WHERE permission_definition.code IN ('platform.authorization.assign', 'workspace.authorization.assign') "
            . "OR permission_definition.code LIKE 'platform.temporary_privileges.%' "
            . "OR permission_definition.code LIKE 'workspace.temporary_privileges.%' "
            . "OR permission_definition.code LIKE 'platform.support_access.%' "
            . "OR permission_definition.code LIKE 'workspace.support_access.%' "
            . "OR permission_definition.code LIKE 'platform.break_glass.%'") !== 0
        ) {
            $errors[] = 'PRIVILEGED_ACCESS_PROHIBITED_POLICY';
        }
        foreach (['uq_privileged_activations_active_session', 'uq_privileged_activations_active_subject'] as $index) {
            if (
                $this->count($pdo, 'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() '
                . "AND table_name = 'privileged_access_activations' AND index_name = '" . $index . "' AND non_unique = 0") !== 2
            ) {
                $errors[] = 'PRIVILEGED_ACCESS_ACTIVATION_UNIQUENESS_INVALID';
                break;
            }
        }
        if (
            $this->count($pdo, "SELECT COUNT(*) FROM privileged_access_activations "
            . "WHERE status = 'ACTIVE' AND expires_at <= UTC_TIMESTAMP(6)") !== 0
        ) {
            $errors[] = 'PRIVILEGED_ACCESS_EXPIRED_ACTIVATION_ACTIVE';
        }
        if (
            $this->count($pdo, "SELECT COUNT(*) FROM privileged_access_activations activation "
            . "INNER JOIN user_sessions session_record ON session_record.id = activation.session_id "
            . "WHERE activation.status = 'ACTIVE' AND (session_record.selected_workspace_id IS NOT NULL "
            . "OR session_record.selected_membership_id IS NOT NULL)") !== 0
        ) {
            $errors[] = 'PRIVILEGED_ACCESS_TENANT_CONTEXT_CONFLICT';
        }

        return new PrivilegedAccessVerificationReport(
            $policyCount,
            $this->count($pdo, 'SELECT COUNT(*) FROM privileged_access_requests'),
            $this->count($pdo, 'SELECT COUNT(*) FROM privileged_access_activations'),
            $this->count($pdo, 'SELECT COUNT(*) FROM privileged_access_reviews'),
            array_values(array_unique($errors)),
        );
    }

    private function count(PDO $pdo, string $sql): int
    {
        $statement = $pdo->query($sql);
        if ($statement === false) {
            return -1;
        }
        $value = $statement->fetchColumn();

        return is_int($value) || is_string($value) && ctype_digit($value) ? (int) $value : -1;
    }
}
