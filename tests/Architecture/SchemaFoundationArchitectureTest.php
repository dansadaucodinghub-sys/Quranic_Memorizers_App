<?php

declare(strict_types=1);

namespace Qmdb\Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\Identity\Infrastructure\Migration\CreateAccountSecurityFoundationMigration;
use Qmdb\Modules\Identity\Infrastructure\Migration\CreateUserAccountsMigration;
use Qmdb\Modules\IdentityResolution\Infrastructure\Migration\CreatePersonCanonicalAliasesMigration;
use Qmdb\Modules\IdentityResolution\Infrastructure\Migration\CreatePersonDuplicateCasesMigration;
use Qmdb\Modules\IdentityResolution\Infrastructure\Migration\CreateProfileClaimPairingAndClaimMigration;
use Qmdb\Modules\IdentityResolution\Infrastructure\Migration\CreateProfileVerificationAssertionsMigration;
use Qmdb\Modules\IdentityResolution\Infrastructure\Migration\ExtendIdentityResolutionReviewIdempotencyMigration;
use Qmdb\Modules\IdentityResolution\Infrastructure\Migration\ExtendIdentityResolutionSecurityCatalogMigration;
use Qmdb\Modules\IdentityResolution\Infrastructure\Seed\SeedPeopleIdentityResolutionAuthorization;
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
use Qmdb\Modules\OrganizationAffiliations\Infrastructure\Migration\CreateOrganizationAffiliationsMigration;
use Qmdb\Modules\OrganizationAffiliations\Infrastructure\Migration\CreateOrganizationAffiliationAssignmentsMigration;
use Qmdb\Modules\OrganizationAffiliations\Infrastructure\Migration\{
    CreateOrganizationAffiliationCatalogAndSecurityMigration,
    ExtendOrganizationAffiliationExpiryNotificationMigration,
};
use Qmdb\Modules\OrganizationAffiliations\Infrastructure\Seed\SeedOrganizationAffiliationAuthorization;
use Qmdb\Modules\OrganizationAffiliations\Infrastructure\Seed\SeedOrganizationAffiliationRoleDefinitions;
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
use Qmdb\Modules\CompetitionLive\Infrastructure\Seed\SeedCompetitionP7AuthorizationCatalog;
use Qmdb\Shared\Background\Scheduler\Migration\CreateScheduledTaskRunsMigration;
use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationRegistry;
use Qmdb\Shared\Schema\Seed\SeedRegistry;
use SplFileInfo;

final class SchemaFoundationArchitectureTest extends TestCase
{
    public function testProductionManifestsPreserveHistoricalPrefixAndAppendAuthorizedP10Extensions(): void
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
        self::assertCount(97, $ordered);
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
                CreateOrganizationAffiliationCatalogAndSecurityMigration::class,
                CreateOrganizationAffiliationsMigration::class,
                CreateOrganizationAffiliationAssignmentsMigration::class,
                ExtendOrganizationAffiliationExpiryNotificationMigration::class,
                CreateProfileClaimPairingAndClaimMigration::class,
                CreateProfileVerificationAssertionsMigration::class,
                CreatePersonDuplicateCasesMigration::class,
                CreatePersonCanonicalAliasesMigration::class,
                ExtendIdentityResolutionSecurityCatalogMigration::class,
                ExtendIdentityResolutionReviewIdempotencyMigration::class,
            ],
            array_slice(array_map(static fn (Migration $migration): string => $migration::class, $ordered), 0, 47),
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
                '20260901040400_create_organization_affiliation_catalog_and_security',
                '20260901040500_create_organization_affiliations',
                '20260901040600_create_organization_affiliation_assignments',
                '20260902040401_extend_organization_affiliation_expiry_notification',
                '20260902050100_create_profile_claim_pairing_and_claim',
                '20260902050200_create_profile_verification_assertions',
                '20260902050300_create_person_duplicate_cases',
                '20260902050400_create_person_canonical_aliases',
                '20260902050500_extend_identity_resolution_security_catalog',
                '20260902050600_extend_identity_resolution_review_idempotency',
            ],
            array_slice(
                array_map(static fn (Migration $migration): string => $migration->id()->value(), $ordered),
                0,
                47,
            ),
        );
        self::assertSame([
            '20260910060100_create_quran_reference_governance',
            '20260910060200_extend_quran_governance_security_catalog',
            '20260910060300_create_quran_release_operation_idempotency',
            '20260910110000_complete_quran_release_manifest_governance',
            '20260911080100_create_quran_canonical_content',
            '20260911080200_enable_quran_system_baseline_actor',
            '20260911103000_create_quran_search_corpus',
            '20260911104500_extend_quran_public_search_rate_limit',
            '20260911110000_complete_quran_search_corpus_security',
            '20260911130000_create_competition_configuration',
            '20260911130100_create_competition_category_configuration',
            '20260911130200_create_competition_registration',
            '20260911130300_harden_competition_tenant_relationships',
            '20260911139900_add_competition_roster_entry_tenant_key',
            '20260911140000_create_competition_judging_scoring',
            '20260912100000_complete_competition_p6_immutable_records',
            '20260912110000_correct_competition_p6_lifecycle_vocabulary',
            '20260912120000_complete_competition_p6_runtime_contracts',
            '20260912133000_add_competition_p6_result_input_uniqueness',
            '20260912140000_create_competition_live_operations',
            '20260912141000_create_competition_live_projections',
            '20260912142000_add_competition_live_operation_sequence',
            '20260912143000_create_competition_result_publication',
            '20260912144000_create_competition_appeal_adjudication',
            '20260913100000_create_competition_p7_outbox',
            '20260913110000_create_competition_p7_operation_receipts',
            '20260913120000_create_competition_result_publication_projections',
            '20260915100000_create_certificate_governance',
            '20260915101000_create_certificate_issuance',
            '20260915102000_create_record_passports',
            '20260915103000_create_trusted_archive',
            '20260915104000_create_legacy_record_import',
            '20260915106000_create_certificate_operation_idempotency',
            '20260915110000_create_media_foundation',
            '20260915111000_extend_media_evidence_runtime',
            '20260915113000_create_media_operation_receipts',
            '20260917120000_complete_media_governance',
            '20260917121000_extend_media_governance_security',
            '20260917140000_create_media_consent_reviews',
        ], array_slice(array_map(static fn (Migration $migration): string => $migration->id()->value(), $ordered), 47, 39));
        self::assertSame([
            '20260922100000_create_recitation_clip_foundation',
            '20260922101000_create_community_safety_foundation',
            '20260922103000_extend_community_security_vocabulary',
            '20260922104000_create_community_operation_receipts',
            '20260922105000_create_community_social_operations',
            '20260922110000_create_community_interaction_events',
            '20260922111000_add_community_feed_keyset_index',
            '20260922112000_add_clip_supersession_constraint',
            '20260922113000_create_community_moderation_appeals',
            '20260922113500_harden_community_moderation_appeal_tenant_key',
            '20260922114000_create_community_notification_intents',
        ], array_map(static fn (Migration $migration): string => $migration->id()->value(), array_slice($ordered, 86)));
        self::assertCount(21, $seeds->ordered());
        self::assertSame(
            [
                SeedFoundationalAuthorizationCatalog::class,
                SeedPrivilegedAccessCatalog::class,
                SeedAccountStateAuthorizationCatalog::class,
                SeedNigeriaAdministrativeGeography::class,
                SeedOrganizationCatalogAndAuthorization::class,
                SeedOrganizationAffiliationRoleDefinitions::class,
                SeedOrganizationAffiliationAuthorization::class,
                SeedPeopleIdentityResolutionAuthorization::class,
            ],
            array_slice(array_map(static fn (object $seed): string => $seed::class, $seeds->ordered()), 0, 8),
        );
        self::assertSame(
            SeedCompetitionP7AuthorizationCatalog::class,
            $seeds->ordered()[17]::class,
        );
        self::assertSame([
            '20260915105000_seed_p8_authorization_catalog',
            '20260915112000_seed_p9_media_authorization_catalog',
        ], array_map(static fn (\Qmdb\Shared\Schema\Seed\Seed $seed): string => $seed->id()->value(), array_slice($seeds->ordered(), 18, 2)));
        self::assertSame(
            '20260922102000_seed_p10_community_authorization',
            $seeds->ordered()[20]->id()->value()
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

    public function testSchemaRetryLedgerAlwaysPersistsTheChecksumOfTheSourceItRuns(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 2) . '/src/Shared/Schema/State/MySqlSchemaStateRepository.php',
        );

        self::assertSame(2, substr_count($source, 'checksum = VALUES(checksum)'));
        self::assertSame(2, substr_count($source, 'description = VALUES(description)'));
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
