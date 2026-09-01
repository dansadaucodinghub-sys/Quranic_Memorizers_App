<?php

declare(strict_types=1);

namespace Qmdb\Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\Identity\Infrastructure\Migration\CreateAccountSecurityFoundationMigration;
use Qmdb\Modules\Identity\Infrastructure\Migration\CreateUserAccountsMigration;
use Qmdb\Modules\Geography\Infrastructure\Migration\CreateGeographyAdministrativeAreaHierarchyMigration;
use Qmdb\Modules\Geography\Infrastructure\Migration\CreateGeographyCountryAndDatasetFoundationMigration;
use Qmdb\Modules\Geography\Infrastructure\Seed\SeedNigeriaAdministrativeGeography;
use Qmdb\Modules\People\Infrastructure\Migration\CreatePeopleGeographyAssociationMigration;
use Qmdb\Modules\People\Infrastructure\Migration\CreatePeopleGuardianshipAndSecurityCatalogMigration;
use Qmdb\Modules\People\Infrastructure\Migration\CreatePeoplePersonFoundationMigration;
use Qmdb\Modules\People\Infrastructure\Migration\CreatePeopleRoleProfilesMigration;
use Qmdb\Modules\Organizations\Infrastructure\Migration\CreateOrganizationClassificationAndSecurityCatalogMigration;
use Qmdb\Modules\Organizations\Infrastructure\Migration\CreateOrganizationsRegistryMigration;
use Qmdb\Modules\Organizations\Infrastructure\Migration\CreateOrganizationUnitsMigration;
use Qmdb\Modules\Organizations\Infrastructure\Seed\SeedOrganizationCatalogAndAuthorization;
use Qmdb\Modules\IdentityAccess\Infrastructure\Migration\CreateIdentityRateLimitFoundationMigration;
use Qmdb\Modules\IdentityAccess\Infrastructure\Migration\CreateIdentityVerificationFoundationMigration;
use Qmdb\Modules\IdentityAccountState\Infrastructure\Migration\CreateAccountStateOperationsMigration;
use Qmdb\Modules\IdentityAccountState\Infrastructure\Seed\SeedAccountStateAuthorizationCatalog;
use Qmdb\Modules\IdentityRecovery\Infrastructure\Migration\CreatePasswordRecoveryFoundationMigration;
use Qmdb\Modules\IdentityRecovery\Infrastructure\Migration\ExtendIdentityRecoveryConstraintsMigration;
use Qmdb\Modules\IdentitySecurityNotifications\Infrastructure\Migration\CreateSecurityNotificationFoundationMigration;
use Qmdb\Modules\IdentitySessions\Infrastructure\Migration\CreateUserDevicesMigration;
use Qmdb\Modules\IdentitySessions\Infrastructure\Migration\CreateUserSessionsMigration;
use Qmdb\Modules\IdentityMultiFactor\Infrastructure\Migration\ExtendIdentityMultiFactorConstraintsMigration;
use Qmdb\Modules\IdentityMultiFactor\Infrastructure\Migration\CreateAuthenticationTransactionFoundationMigration;
use Qmdb\Modules\IdentityMultiFactor\Infrastructure\Migration\CreateTotpRecoveryCodeFoundationMigration;
use Qmdb\Modules\IdentityMultiFactor\Infrastructure\Migration\CreatePasskeyFoundationMigration;
use Qmdb\Modules\Tenancy\Infrastructure\Migration\CreateWorkspaceMembershipsMigration;
use Qmdb\Modules\Tenancy\Infrastructure\Migration\CreateWorkspacesMigration;
use Qmdb\Modules\TenancyContext\Infrastructure\Migration\AddSessionBoundTenantContextMigration;
use Qmdb\Modules\SecurityAuthorization\Infrastructure\Migration\CreateAuthorizationCatalogFoundationMigration;
use Qmdb\Modules\SecurityAuthorization\Infrastructure\Migration\CreatePlatformRoleAssignmentFoundationMigration;
use Qmdb\Modules\SecurityAuthorization\Infrastructure\Migration\CreateWorkspaceRoleAssignmentFoundationMigration;
use Qmdb\Modules\SecurityAuthorization\Infrastructure\Seed\SeedFoundationalAuthorizationCatalog;
use Qmdb\Modules\SecurityAudit\Infrastructure\Migration\CreateSecurityAuditCheckpointsMigration;
use Qmdb\Modules\SecurityAudit\Infrastructure\Migration\CreateSecurityAuditStreamsMigration;
use Qmdb\Modules\SecurityAudit\Infrastructure\Migration\ExtendAccountStateSecurityCatalogMigration;
use Qmdb\Modules\SecurityAudit\Infrastructure\Migration\PreserveCanonicalAuditMetadataMigration;
use Qmdb\Modules\SecurityPrivilegedAccess\Infrastructure\Migration\CreatePrivilegedAccessActivationFoundationMigration;
use Qmdb\Modules\SecurityPrivilegedAccess\Infrastructure\Migration\CreatePrivilegedAccessRequestFoundationMigration;
use Qmdb\Modules\SecurityPrivilegedAccess\Infrastructure\Migration\ExtendPrivilegedAccessSecurityCatalogMigration;
use Qmdb\Modules\SecurityPrivilegedAccess\Infrastructure\Seed\SeedPrivilegedAccessCatalog;
use Qmdb\Shared\Background\Scheduler\Migration\CreateScheduledTaskRunsMigration;
use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationRegistry;
use Qmdb\Shared\Schema\Seed\SeedRegistry;
use SplFileInfo;

final class SchemaFoundationArchitectureTest extends TestCase
{
    public function testProductionManifestsContainAuthorizedP1ThroughP3B02SchemaChanges(): void
    {
        $migrationFactory = require dirname(__DIR__, 2) . '/database/migrations.php';
        $seedFactory = require dirname(__DIR__, 2) . '/database/seeds.php';

        if (!is_callable($migrationFactory) || !is_callable($seedFactory)) {
            self::fail('Production schema manifests must return callable factories.');
        }
        $migrations = $migrationFactory();
        $seeds = $seedFactory();
        if (!$migrations instanceof MigrationRegistry || !$seeds instanceof SeedRegistry) {
            self::fail('Production schema manifests returned invalid registries.');
        }

        $ordered = $migrations->ordered();
        self::assertCount(37, $ordered);
        self::assertSame(
            [
                CreateScheduledTaskRunsMigration::class,
                CreateWorkspacesMigration::class,
                CreateUserAccountsMigration::class,
                CreateAccountSecurityFoundationMigration::class,
                CreateWorkspaceMembershipsMigration::class,
                CreateIdentityVerificationFoundationMigration::class,
                CreateIdentityRateLimitFoundationMigration::class,
                CreateUserDevicesMigration::class,
                CreateUserSessionsMigration::class,
                ExtendIdentityRecoveryConstraintsMigration::class,
                CreatePasswordRecoveryFoundationMigration::class,
                CreateSecurityNotificationFoundationMigration::class,
                ExtendIdentityMultiFactorConstraintsMigration::class,
                CreateAuthenticationTransactionFoundationMigration::class,
                CreateTotpRecoveryCodeFoundationMigration::class,
                CreatePasskeyFoundationMigration::class,
                CreateAuthorizationCatalogFoundationMigration::class,
                CreatePlatformRoleAssignmentFoundationMigration::class,
                CreateWorkspaceRoleAssignmentFoundationMigration::class,
                AddSessionBoundTenantContextMigration::class,
                ExtendPrivilegedAccessSecurityCatalogMigration::class,
                CreatePrivilegedAccessRequestFoundationMigration::class,
                CreatePrivilegedAccessActivationFoundationMigration::class,
                ExtendAccountStateSecurityCatalogMigration::class,
                CreateSecurityAuditStreamsMigration::class,
                CreateSecurityAuditCheckpointsMigration::class,
                CreateAccountStateOperationsMigration::class,
                PreserveCanonicalAuditMetadataMigration::class,
                CreateGeographyCountryAndDatasetFoundationMigration::class,
                CreateGeographyAdministrativeAreaHierarchyMigration::class,
                CreatePeoplePersonFoundationMigration::class,
                CreatePeopleGeographyAssociationMigration::class,
                CreatePeopleRoleProfilesMigration::class,
                CreatePeopleGuardianshipAndSecurityCatalogMigration::class,
                CreateOrganizationClassificationAndSecurityCatalogMigration::class,
                CreateOrganizationsRegistryMigration::class,
                CreateOrganizationUnitsMigration::class,
            ],
            array_map(static fn (Migration $migration): string => $migration::class, $ordered),
        );
        self::assertSame(
            [
                '20260825000100_create_scheduled_task_runs',
                '20260826010100_create_workspaces',
                '20260826010200_create_user_accounts',
                '20260826010300_create_account_security_foundation',
                '20260826010400_create_workspace_memberships',
                '20260826010500_create_identity_verification_foundation',
                '20260826010600_create_identity_rate_limit_foundation',
                '20260826010700_create_user_devices',
                '20260826010800_create_user_sessions',
                '20260826010900_extend_identity_recovery_constraints',
                '20260826011000_create_password_recovery_foundation',
                '20260826011100_create_security_notification_foundation',
                '20260826011200_extend_identity_multifactor_constraints',
                '20260826011300_create_authentication_transaction_foundation',
                '20260826011400_create_totp_recovery_code_foundation',
                '20260826011500_create_passkey_foundation',
                '20260826011600_create_authorization_catalog_foundation',
                '20260826011700_create_platform_role_assignment_foundation',
                '20260826011800_create_workspace_role_assignment_foundation',
                '20260826011900_add_session_bound_tenant_context',
                '20260826012000_extend_privileged_access_security_catalog',
                '20260826012100_create_privileged_access_request_foundation',
                '20260826012200_create_privileged_access_activation_foundation',
                '20260826012300_extend_account_state_security_catalog',
                '20260826012400_create_security_audit_streams',
                '20260826012500_create_security_audit_checkpoints',
                '20260826012600_create_account_state_operations',
                '20260826012700_preserve_canonical_audit_metadata',
                '20260831000100_create_geography_country_and_dataset_foundation',
                '20260831000200_create_geography_administrative_area_hierarchy',
                '20260901030100_create_people_person_foundation',
                '20260901030200_create_people_geography_associations',
                '20260901030300_create_people_role_profiles',
                '20260901030400_create_people_guardianship_and_security_catalog',
                '20260901040100_create_organization_classification_and_security_catalog',
                '20260901040200_create_organizations_registry',
                '20260901040300_create_organization_units',
            ],
            array_map(static fn (Migration $migration): string => $migration->id()->value(), $ordered),
        );
        self::assertCount(5, $seeds->ordered());
        self::assertSame(
            [
                SeedFoundationalAuthorizationCatalog::class,
                SeedPrivilegedAccessCatalog::class,
                SeedAccountStateAuthorizationCatalog::class,
                SeedNigeriaAdministrativeGeography::class,
                SeedOrganizationCatalogAndAuthorization::class,
            ],
            array_map(static fn (object $seed): string => $seed::class, $seeds->ordered()),
        );
    }

    public function testNoDiscoveryOrHttpMutationSurfaceExists(): void
    {
        $root = dirname(__DIR__, 2);
        $schemaSource = $this->sourceUnder($root . '/src/Shared/Schema');
        $routeSource = (string) file_get_contents($root . '/routes/web.php');

        self::assertStringNotContainsString('glob(', $schemaSource);
        self::assertStringNotContainsString('RecursiveDirectoryIterator', $schemaSource);
        self::assertStringNotContainsString('ReflectionClass', $schemaSource);
        self::assertStringNotContainsString('SET FOREIGN_KEY_CHECKS', $this->frameworkSqlOnly($schemaSource));
        self::assertStringNotContainsString('SET GLOBAL', $this->frameworkSqlOnly($schemaSource));
        self::assertStringNotContainsString('db:migrate', $routeSource);
        self::assertStringNotContainsString('db:seed', $routeSource);
    }

    public function testNoFrontendOrThirdPartyMigrationFrameworkWasIntroduced(): void
    {
        $root = dirname(__DIR__, 2);
        $composer = (string) file_get_contents($root . '/composer.json');
        self::assertStringNotContainsString('doctrine/migrations', strtolower($composer));
        self::assertStringNotContainsString('robmorgan/phinx', strtolower($composer));
        self::assertDirectoryDoesNotExist($root . '/src/Shared/Schema/Http');
        self::assertDirectoryDoesNotExist($root . '/src/Shared/Schema/Frontend');
    }

    public function testSchemaProviderUsesPhp85PdoMysqlConstants(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 2) . '/src/Shared/Schema/Connection/MySqlSchemaConnectionProvider.php',
        );

        self::assertStringNotContainsString('PDO::MYSQL_ATTR_', $source);
        self::assertStringContainsString('\\Pdo\\Mysql::ATTR_MULTI_STATEMENTS', $source);
        self::assertStringContainsString('\\Pdo\\Mysql::ATTR_SSL_CA', $source);
        self::assertStringContainsString('\\Pdo\\Mysql::ATTR_SSL_VERIFY_SERVER_CERT', $source);
    }

    private function sourceUnder(string $directory): string
    {
        $source = '';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $source .= (string) file_get_contents($file->getPathname());
            }
        }

        return $source;
    }

    private function frameworkSqlOnly(string $source): string
    {
        return str_replace([
            "'SET FOREIGN_KEY_CHECKS'",
            "'SET GLOBAL'",
            'SET\\s+FOREIGN_KEY_CHECKS',
            'SET\\s+GLOBAL',
        ], '', $source);
    }
}
