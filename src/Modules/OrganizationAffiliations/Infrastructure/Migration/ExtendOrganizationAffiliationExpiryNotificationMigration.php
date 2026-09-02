<?php

declare(strict_types=1);

namespace Qmdb\Modules\OrganizationAffiliations\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/** Forward-only correction: expiry is not a decline. */
final readonly class ExtendOrganizationAffiliationExpiryNotificationMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260902040401_extend_organization_affiliation_expiry_notification');
    }
    public function description(): string
    {
        return 'Add the distinct Organization affiliation expiry security-notification type.';
    }
    public function dependencies(): array
    {
        return [(new CreateOrganizationAffiliationCatalogAndSecurityMigration())->id()];
    }

    public function up(): array
    {
        return [new SqlMigrationStep(new MigrationStepId('001_extend_notification_types'), 'Allow the Organization affiliation expiry security notification.', <<<'SQL'
ALTER TABLE account_security_notifications DROP CHECK ck_security_notifications_type,
 ADD CONSTRAINT ck_security_notifications_type CHECK (notification_type IN (
 'PASSWORD_RESET_COMPLETED','MFA_ENABLED','MFA_DISABLED','TOTP_AUTHENTICATOR_ADDED','TOTP_AUTHENTICATOR_REMOVED','PASSKEY_ADDED','PASSKEY_REMOVED','RECOVERY_CODES_REGENERATED','RECOVERY_CODE_USED','PASSKEY_SUSPENDED','PLATFORM_ROLE_ASSIGNED','PLATFORM_ROLE_REVOKED','WORKSPACE_ROLE_ASSIGNED','WORKSPACE_ROLE_REVOKED','TEMPORARY_PRIVILEGE_REQUESTED','TEMPORARY_PRIVILEGE_APPROVED','TEMPORARY_PRIVILEGE_REJECTED','TEMPORARY_PRIVILEGE_ACTIVATED','TEMPORARY_PRIVILEGE_REVOKED','TEMPORARY_PRIVILEGE_EXPIRED','SUPPORT_ACCESS_REQUESTED','SUPPORT_ACCESS_PARTIALLY_APPROVED','SUPPORT_ACCESS_APPROVED','SUPPORT_ACCESS_ACTIVATED','SUPPORT_ACCESS_ENDED','SUPPORT_ACCESS_REVOKED','SUPPORT_ACCESS_REVIEW_REQUIRED','SUPPORT_ACCESS_REVIEW_OVERDUE','SUPPORT_ACCESS_REVIEW_COMPLETED','BREAK_GLASS_ACTIVATED','BREAK_GLASS_ENDED','BREAK_GLASS_EXPIRED','BREAK_GLASS_REVIEW_REQUIRED','BREAK_GLASS_REVIEW_OVERDUE','BREAK_GLASS_REVIEW_COMPLETED','ACCOUNT_SUSPENDED','ACCOUNT_REACTIVATED','PERSON_PROFILE_CREATED','PERSON_PROFILE_UPDATED','DEPENDENT_PROFILE_CREATED','GUARDIANSHIP_REVOKED',
 'ORGANIZATION_AFFILIATION_REQUESTED','ORGANIZATION_AFFILIATION_ACCEPTED','ORGANIZATION_AFFILIATION_DECLINED','ORGANIZATION_AFFILIATION_EXPIRED','ORGANIZATION_AFFILIATION_SUSPENDED','ORGANIZATION_AFFILIATION_RESUMED','ORGANIZATION_AFFILIATION_ENDED','ORGANIZATION_AFFILIATION_ASSIGNMENTS_CHANGED','ORGANIZATION_LEADERSHIP_CHANGED'
 ))
SQL)];
    }

    public function down(): array
    {
        return [];
    }
    public function reversible(): bool
    {
        return false;
    }
}
