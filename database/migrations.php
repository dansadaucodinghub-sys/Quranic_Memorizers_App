<?php

declare(strict_types=1);

use Qmdb\Shared\Background\Scheduler\Migration\CreateScheduledTaskRunsMigration;
use Qmdb\Modules\Identity\Infrastructure\Migration\CreateAccountSecurityFoundationMigration;
use Qmdb\Modules\Identity\Infrastructure\Migration\CreateUserAccountsMigration;
use Qmdb\Modules\IdentityAccess\Infrastructure\Migration\CreateIdentityRateLimitFoundationMigration;
use Qmdb\Modules\IdentityAccess\Infrastructure\Migration\CreateIdentityVerificationFoundationMigration;
use Qmdb\Modules\IdentitySessions\Infrastructure\Migration\CreateUserDevicesMigration;
use Qmdb\Modules\IdentitySessions\Infrastructure\Migration\CreateUserSessionsMigration;
use Qmdb\Modules\IdentityRecovery\Infrastructure\Migration\CreatePasswordRecoveryFoundationMigration;
use Qmdb\Modules\IdentityRecovery\Infrastructure\Migration\ExtendIdentityRecoveryConstraintsMigration;
use Qmdb\Modules\IdentitySecurityNotifications\Infrastructure\Migration\CreateSecurityNotificationFoundationMigration;
use Qmdb\Modules\IdentityMultiFactor\Infrastructure\Migration\CreateAuthenticationTransactionFoundationMigration;
use Qmdb\Modules\IdentityMultiFactor\Infrastructure\Migration\CreatePasskeyFoundationMigration;
use Qmdb\Modules\IdentityMultiFactor\Infrastructure\Migration\CreateTotpRecoveryCodeFoundationMigration;
use Qmdb\Modules\IdentityMultiFactor\Infrastructure\Migration\ExtendIdentityMultiFactorConstraintsMigration;
use Qmdb\Modules\SecurityAuthorization\Infrastructure\Migration\CreateAuthorizationCatalogFoundationMigration;
use Qmdb\Modules\SecurityAuthorization\Infrastructure\Migration\CreatePlatformRoleAssignmentFoundationMigration;
use Qmdb\Modules\SecurityAuthorization\Infrastructure\Migration\CreateWorkspaceRoleAssignmentFoundationMigration;
use Qmdb\Modules\Tenancy\Infrastructure\Migration\CreateWorkspaceMembershipsMigration;
use Qmdb\Modules\Tenancy\Infrastructure\Migration\CreateWorkspacesMigration;
use Qmdb\Modules\TenancyContext\Infrastructure\Migration\AddSessionBoundTenantContextMigration;
use Qmdb\Modules\SecurityPrivilegedAccess\Infrastructure\Migration\ExtendPrivilegedAccessSecurityCatalogMigration;
use Qmdb\Modules\SecurityPrivilegedAccess\Infrastructure\Migration\CreatePrivilegedAccessRequestFoundationMigration;
use Qmdb\Modules\SecurityPrivilegedAccess\Infrastructure\Migration\CreatePrivilegedAccessActivationFoundationMigration;
use Qmdb\Modules\SecurityAudit\Infrastructure\Migration\ExtendAccountStateSecurityCatalogMigration;
use Qmdb\Modules\SecurityAudit\Infrastructure\Migration\CreateSecurityAuditStreamsMigration;
use Qmdb\Modules\SecurityAudit\Infrastructure\Migration\CreateSecurityAuditCheckpointsMigration;
use Qmdb\Modules\IdentityAccountState\Infrastructure\Migration\CreateAccountStateOperationsMigration;
use Qmdb\Modules\SecurityAudit\Infrastructure\Migration\PreserveCanonicalAuditMetadataMigration;
use Qmdb\Modules\Geography\Infrastructure\Migration\CreateGeographyCountryAndDatasetFoundationMigration;
use Qmdb\Modules\Geography\Infrastructure\Migration\CreateGeographyAdministrativeAreaHierarchyMigration;
use Qmdb\Modules\People\Infrastructure\Migration\CreatePeoplePersonFoundationMigration;
use Qmdb\Modules\People\Infrastructure\Migration\CreatePeopleGeographyAssociationMigration;
use Qmdb\Modules\People\Infrastructure\Migration\CreatePeopleRoleProfilesMigration;
use Qmdb\Modules\People\Infrastructure\Migration\CreatePeopleGuardianshipAndSecurityCatalogMigration;
use Qmdb\Modules\Organizations\Infrastructure\Migration\CreateOrganizationClassificationAndSecurityCatalogMigration;
use Qmdb\Modules\Organizations\Infrastructure\Migration\CreateOrganizationsRegistryMigration;
use Qmdb\Modules\Organizations\Infrastructure\Migration\CreateOrganizationUnitsMigration;
use Qmdb\Modules\OrganizationAffiliations\Infrastructure\Migration\CreateOrganizationAffiliationCatalogAndSecurityMigration;
use Qmdb\Modules\OrganizationAffiliations\Infrastructure\Migration\CreateOrganizationAffiliationsMigration;
use Qmdb\Modules\OrganizationAffiliations\Infrastructure\Migration\CreateOrganizationAffiliationAssignmentsMigration;
use Qmdb\Modules\OrganizationAffiliations\Infrastructure\Migration\ExtendOrganizationAffiliationExpiryNotificationMigration;
use Qmdb\Shared\Schema\Migration\MigrationRegistry;
use Qmdb\Shared\Schema\Migration\MigrationRegistryBuilder;

return static function (): MigrationRegistry {
    return (new MigrationRegistryBuilder())
        ->register(new CreateScheduledTaskRunsMigration())
        ->register(new CreateWorkspacesMigration())
        ->register(new CreateUserAccountsMigration())
        ->register(new CreateAccountSecurityFoundationMigration())
        ->register(new CreateWorkspaceMembershipsMigration())
        ->register(new CreateIdentityVerificationFoundationMigration())
        ->register(new CreateIdentityRateLimitFoundationMigration())
        ->register(new CreateUserDevicesMigration())
        ->register(new CreateUserSessionsMigration())
        ->register(new ExtendIdentityRecoveryConstraintsMigration())
        ->register(new CreatePasswordRecoveryFoundationMigration())
        ->register(new CreateSecurityNotificationFoundationMigration())
        ->register(new ExtendIdentityMultiFactorConstraintsMigration())
        ->register(new CreateAuthenticationTransactionFoundationMigration())
        ->register(new CreateTotpRecoveryCodeFoundationMigration())
        ->register(new CreatePasskeyFoundationMigration())
        ->register(new CreateAuthorizationCatalogFoundationMigration())
        ->register(new CreatePlatformRoleAssignmentFoundationMigration())
        ->register(new CreateWorkspaceRoleAssignmentFoundationMigration())
        ->register(new AddSessionBoundTenantContextMigration())
        ->register(new ExtendPrivilegedAccessSecurityCatalogMigration())
        ->register(new CreatePrivilegedAccessRequestFoundationMigration())
        ->register(new CreatePrivilegedAccessActivationFoundationMigration())
        ->register(new ExtendAccountStateSecurityCatalogMigration())
        ->register(new CreateSecurityAuditStreamsMigration())
        ->register(new CreateSecurityAuditCheckpointsMigration())
        ->register(new CreateAccountStateOperationsMigration())
        ->register(new PreserveCanonicalAuditMetadataMigration())
        ->register(new CreateGeographyCountryAndDatasetFoundationMigration())
        ->register(new CreateGeographyAdministrativeAreaHierarchyMigration())
        ->register(new CreatePeoplePersonFoundationMigration())
        ->register(new CreatePeopleGeographyAssociationMigration())
        ->register(new CreatePeopleRoleProfilesMigration())
        ->register(new CreatePeopleGuardianshipAndSecurityCatalogMigration())
        ->register(new CreateOrganizationClassificationAndSecurityCatalogMigration())
        ->register(new CreateOrganizationsRegistryMigration())
        ->register(new CreateOrganizationUnitsMigration())
        ->register(new CreateOrganizationAffiliationCatalogAndSecurityMigration())
        ->register(new CreateOrganizationAffiliationsMigration())
        ->register(new CreateOrganizationAffiliationAssignmentsMigration())
        ->register(new ExtendOrganizationAffiliationExpiryNotificationMigration())
        ->build();
};
