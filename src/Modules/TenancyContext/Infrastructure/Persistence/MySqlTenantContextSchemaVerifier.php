<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Infrastructure\Persistence;

use PDO;
use Qmdb\Modules\TenancyContext\Application\TenantContextSchemaVerifier;
use Qmdb\Modules\TenancyContext\Application\TenantContextVerificationReport;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;

final readonly class MySqlTenantContextSchemaVerifier implements TenantContextSchemaVerifier
{
    public function __construct(
        private DatabaseConnectionProvider $provider,
        private string $projectRoot,
    ) {
    }

    public function verify(): TenantContextVerificationReport
    {
        $pdo = $this->provider->connection();
        $columns = $this->names($pdo, 'SELECT column_name FROM information_schema.columns '
            . "WHERE table_schema = DATABASE() AND table_name = 'user_sessions' "
            . "AND column_name IN ('selected_workspace_id','selected_membership_id',"
            . "'tenant_context_version','tenant_context_selected_at')");
        $constraints = $this->names($pdo, 'SELECT constraint_name FROM information_schema.table_constraints '
            . "WHERE table_schema = DATABASE() AND table_name IN ('user_sessions','workspace_memberships') "
            . "AND constraint_name IN ('fk_user_sessions_selected_workspace_membership_account',"
            . "'ck_user_sessions_tenant_context_version','ck_user_sessions_tenant_context_complete',"
            . "'uq_workspace_memberships_workspace_account_id')");
        $errors = [];
        if (count($columns) !== 4) {
            $errors[] = 'Session tenant-context columns are incomplete.';
        }
        if (count($constraints) !== 4) {
            $errors[] = 'Tenant-context integrity constraints are incomplete.';
        }
        if (
            $this->count($pdo, "SELECT COUNT(*) FROM qmdb_schema_migrations "
            . "WHERE migration_id = '20260826011900_add_session_bound_tenant_context' "
            . "AND status = 'APPLIED'") !== 1
        ) {
            $errors[] = 'Tenant-context migration is not applied.';
        }
        if (
            $this->count($pdo, 'SELECT COUNT(*) FROM information_schema.columns '
            . "WHERE table_schema = DATABASE() AND table_name = 'user_sessions' AND ("
            . "(column_name = 'selected_workspace_id' AND column_type = 'bigint unsigned' AND is_nullable = 'YES') "
            . "OR (column_name = 'selected_membership_id' AND column_type = 'bigint unsigned' "
            . "AND is_nullable = 'YES') OR (column_name = 'tenant_context_version' "
            . "AND column_type = 'bigint unsigned' AND is_nullable = 'NO' AND column_default = '1') "
            . "OR (column_name = 'tenant_context_selected_at' AND data_type = 'datetime' "
            . "AND datetime_precision = 6 AND is_nullable = 'YES'))") !== 4
        ) {
            $errors[] = 'Tenant-context column definitions are invalid.';
        }
        if (
            $this->count($pdo, 'SELECT COUNT(DISTINCT index_name) FROM information_schema.statistics '
            . "WHERE table_schema = DATABASE() AND table_name = 'user_sessions' AND index_name IN "
            . "('ix_user_sessions_account_workspace_status','ix_user_sessions_selected_membership_status')") !== 2
        ) {
            $errors[] = 'Tenant-context indexes are incomplete.';
        }
        if (
            $this->count($pdo, 'SELECT COUNT(*) FROM information_schema.statistics '
            . "WHERE table_schema = DATABASE() AND table_name = 'workspace_memberships' "
            . "AND index_name = 'uq_workspace_memberships_workspace_account_id' AND non_unique = 0") !== 3
        ) {
            $errors[] = 'Tenant-context membership candidate key is invalid.';
        }
        if (
            $this->count($pdo, 'SELECT COUNT(*) FROM information_schema.referential_constraints '
            . "WHERE constraint_schema = DATABASE() "
            . "AND constraint_name = 'fk_user_sessions_selected_workspace_membership_account' "
            . "AND unique_constraint_name = 'uq_workspace_memberships_workspace_account_id' "
            . "AND update_rule = 'RESTRICT' AND delete_rule = 'RESTRICT'") !== 1
        ) {
            $errors[] = 'Tenant-context composite foreign key rules are invalid.';
        }
        if (
            $this->count($pdo, 'SELECT COUNT(*) FROM information_schema.key_column_usage '
            . "WHERE constraint_schema = DATABASE() AND table_name = 'user_sessions' "
            . "AND constraint_name = 'fk_user_sessions_selected_workspace_membership_account' AND ("
            . "(ordinal_position = 1 AND column_name = 'selected_workspace_id' "
            . "AND referenced_column_name = 'workspace_id') OR "
            . "(ordinal_position = 2 AND column_name = 'account_id' "
            . "AND referenced_column_name = 'user_account_id') OR "
            . "(ordinal_position = 3 AND column_name = 'selected_membership_id' "
            . "AND referenced_column_name = 'id'))") !== 3
        ) {
            $errors[] = 'Tenant-context composite foreign key columns are invalid.';
        }
        if (
            $this->count($pdo, 'SELECT COUNT(*) FROM information_schema.tables '
            . "WHERE table_schema = DATABASE() AND table_name IN ('user_sessions','workspace_memberships') "
            . "AND engine = 'InnoDB'") !== 2
        ) {
            $errors[] = 'Tenant-context tables must use InnoDB.';
        }
        $this->verifySourceBoundaries($errors);

        return new TenantContextVerificationReport($errors);
    }

    /** @param list<string> $errors */
    private function verifySourceBoundaries(array &$errors): void
    {
        $module = $this->content('src/Bootstrap/ApplicationFactory.php');
        if (!str_contains($module, 'new TenancyContextModule($this->projectRoot)')) {
            $errors[] = 'Tenant-context module wiring is invalid.';
        }
        $http = $this->content('src/Bootstrap/Module/ApplicationHttpModule.php');
        $authentication = strpos($http, 'SessionAuthenticationMiddleware::class');
        $tenantContext = strpos($http, 'TenantContextMiddleware::class');
        if ($authentication === false || $tenantContext === false || $tenantContext <= $authentication) {
            $errors[] = 'Tenant-context middleware order is invalid.';
        }
        $attributes = $this->content('src/Modules/TenancyContext/Application/TenantContextAttributes.php');
        if (
            !str_contains($attributes, "CONTEXT = 'qmdb.tenant_context'")
            || !str_contains($attributes, "VERSION = 'qmdb.tenant_context_version'")
        ) {
            $errors[] = 'Tenant-context request attributes are invalid.';
        }
        foreach (
            [
                'src/Modules/Tenancy/Domain/Repository/WorkspaceMembershipRepository.php',
                'src/Modules/SecurityAuthorization/Domain/Repository/WorkspaceRoleAssignmentRepository.php',
            ] as $path
        ) {
            $repository = $this->content($path);
            if (
                !str_contains($repository, 'extends TenantScopedRepository')
                || !str_contains($repository, 'TenantContext $context')
            ) {
                $errors[] = 'Tenant-scoped repository signatures are invalid.';
                break;
            }
        }
        $client = $this->content('public/assets/js/tenant-context-controller.js');
        $workspaceAuthorityHeader = 'X-Workspace' . '-ID';
        foreach (['localStorage', 'sessionStorage', 'document.cookie', $workspaceAuthorityHeader] as $forbidden) {
            if (str_contains($client, $forbidden)) {
                $errors[] = 'Client tenant-context authority boundary is invalid.';
                break;
            }
        }
        $source = '';
        foreach (
            [
                'src/Bootstrap/Module/TenancyContextModule.php',
                'src/Modules/TenancyContext/Application/SessionTenantContextResolver.php',
                'src/Modules/TenancyContext/Application/WorkspaceContextClearingService.php',
                'src/Modules/TenancyContext/Application/WorkspaceContextSelectionService.php',
                'src/Modules/TenancyContext/Domain/AccountWorkspaceTenantContext.php',
                'src/Modules/TenancyContext/Interface/Http/TenantContextMiddleware.php',
                'src/Modules/SecurityAuthorization/Domain/WorkspaceAuthorizationScope.php',
            ] as $path
        ) {
            $source .= "\n" . $this->content($path);
        }
        if (
            preg_match('/static\s+(?:\??AccountWorkspaceTenantContext|TenantContext)\b/', $source) === 1
            || str_contains($source, $workspaceAuthorityHeader)
        ) {
            $errors[] = 'Server tenant-context authority boundary is invalid.';
        }
        foreach (
            [
                'Qmdb\Bootstrap\Module\TenancyContextModule',
                'Qmdb\Modules\TenancyContext\Interface\Http\TenantContextMiddleware',
                'Qmdb\Modules\SecurityAuthorization\Domain\WorkspaceAuthorizationScope',
                'Qmdb\Modules\TenancyContext\Application\Background\TenantBoundBackgroundJobContextResolver',
                self::class,
            ] as $class
        ) {
            if (!class_exists($class)) {
                $errors[] = 'Tenant-context runtime types do not compile.';
                break;
            }
        }
    }

    private function content(string $relative): string
    {
        $content = file_get_contents($this->projectRoot . '/' . $relative);

        return is_string($content) ? $content : '';
    }

    /** @return list<string> */
    private function names(PDO $pdo, string $sql): array
    {
        $statement = $pdo->query($sql);
        if ($statement === false) {
            return [];
        }

        return array_values(array_filter($statement->fetchAll(PDO::FETCH_COLUMN), 'is_string'));
    }

    private function count(PDO $pdo, string $sql): int
    {
        $statement = $pdo->query($sql);
        if ($statement === false) {
            return -1;
        }
        $value = $statement->fetchColumn();
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value) || preg_match('/\A[0-9]+\z/', $value) !== 1) {
            return -1;
        }

        return (int)$value;
    }
}
