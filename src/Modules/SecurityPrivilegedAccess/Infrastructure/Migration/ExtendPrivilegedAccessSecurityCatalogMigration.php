<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace Qmdb\Modules\SecurityPrivilegedAccess\Infrastructure\Migration;

use Qmdb\Modules\TenancyContext\Infrastructure\Migration\AddSessionBoundTenantContextMigration;
use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class ExtendPrivilegedAccessSecurityCatalogMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260826012000_extend_privileged_access_security_catalog');
    }

    public function description(): string
    {
        return 'Extend step-up, rate-limit and notification catalogs for controlled privileged access.';
    }

    public function dependencies(): array
    {
        return [(new AddSessionBoundTenantContextMigration())->id()];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_extend_step_up_actions'), 'Add privileged-access step-up actions.', <<<'SQL'
ALTER TABLE account_step_up_grants
    DROP CHECK ck_step_up_grants_action,
    ADD CONSTRAINT ck_step_up_grants_action CHECK (action IN (
        'MFA_ENROLL_TOTP','MFA_REGISTER_PASSKEY','MFA_ENABLE','MFA_DISABLE',
        'MFA_REGENERATE_RECOVERY_CODES','MFA_REVOKE_TOTP','MFA_REVOKE_PASSKEY',
        'AUTHORIZATION_PLATFORM_ROLE_ASSIGN','AUTHORIZATION_PLATFORM_ROLE_REVOKE',
        'AUTHORIZATION_WORKSPACE_ROLE_ASSIGN','AUTHORIZATION_WORKSPACE_ROLE_REVOKE',
        'TEMPORARY_PRIVILEGE_APPROVE','TEMPORARY_PRIVILEGE_ACTIVATE','TEMPORARY_PRIVILEGE_REVOKE',
        'SUPPORT_ACCESS_PLATFORM_APPROVE','SUPPORT_ACCESS_WORKSPACE_APPROVE','SUPPORT_ACCESS_ACTIVATE',
        'SUPPORT_ACCESS_REVOKE','SUPPORT_ACCESS_REVIEW','BREAK_GLASS_ACTIVATE','BREAK_GLASS_REVIEW'
    ))
SQL),
            new SqlMigrationStep(new MigrationStepId('002_extend_rate_limit_scopes'), 'Add privileged-access rate-limit scopes.', <<<'SQL'
ALTER TABLE identity_rate_limit_buckets
    DROP CHECK ck_identity_rate_limit_scope,
    ADD CONSTRAINT ck_identity_rate_limit_scope CHECK (scope IN (
        'ACCOUNT_REGISTRATION_EMAIL','ACCOUNT_REGISTRATION_PEER',
        'EMAIL_VERIFICATION_RESEND_EMAIL','EMAIL_VERIFICATION_RESEND_PEER',
        'EMAIL_VERIFICATION_ATTEMPT','EMAIL_VERIFICATION_PEER',
        'PASSWORD_AUTHENTICATION_EMAIL','PASSWORD_AUTHENTICATION_PEER',
        'PASSWORD_RECOVERY_REQUEST_EMAIL','PASSWORD_RECOVERY_REQUEST_PEER',
        'PASSWORD_RECOVERY_ATTEMPT','PASSWORD_RECOVERY_ATTEMPT_PEER',
        'MFA_AUTHENTICATION_ACCOUNT','MFA_AUTHENTICATION_PEER','PASSKEY_AUTHENTICATION_PEER',
        'STEP_UP_ACCOUNT','STEP_UP_PEER','PASSKEY_REGISTRATION_ACCOUNT',
        'PRIVILEGED_ACCESS_REQUEST_ACCOUNT','PRIVILEGED_ACCESS_REQUEST_PEER',
        'PRIVILEGED_ACCESS_APPROVAL_ACCOUNT','PRIVILEGED_ACCESS_ACTIVATION_ACCOUNT',
        'BREAK_GLASS_ACTIVATION_ACCOUNT','BREAK_GLASS_ACTIVATION_PEER'
    ))
SQL),
            new SqlMigrationStep(new MigrationStepId('003_extend_security_notification_types'), 'Add privileged-access security notification types.', <<<'SQL'
ALTER TABLE account_security_notifications
    DROP CHECK ck_security_notifications_type,
    ADD CONSTRAINT ck_security_notifications_type CHECK (notification_type IN (
        'PASSWORD_RESET_COMPLETED','MFA_ENABLED','MFA_DISABLED','TOTP_AUTHENTICATOR_ADDED',
        'TOTP_AUTHENTICATOR_REMOVED','PASSKEY_ADDED','PASSKEY_REMOVED',
        'RECOVERY_CODES_REGENERATED','RECOVERY_CODE_USED','PASSKEY_SUSPENDED',
        'PLATFORM_ROLE_ASSIGNED','PLATFORM_ROLE_REVOKED','WORKSPACE_ROLE_ASSIGNED','WORKSPACE_ROLE_REVOKED',
        'TEMPORARY_PRIVILEGE_REQUESTED','TEMPORARY_PRIVILEGE_APPROVED','TEMPORARY_PRIVILEGE_REJECTED',
        'TEMPORARY_PRIVILEGE_ACTIVATED','TEMPORARY_PRIVILEGE_REVOKED','TEMPORARY_PRIVILEGE_EXPIRED',
        'SUPPORT_ACCESS_REQUESTED','SUPPORT_ACCESS_PARTIALLY_APPROVED','SUPPORT_ACCESS_APPROVED',
        'SUPPORT_ACCESS_ACTIVATED','SUPPORT_ACCESS_ENDED','SUPPORT_ACCESS_REVOKED',
        'SUPPORT_ACCESS_REVIEW_REQUIRED','SUPPORT_ACCESS_REVIEW_OVERDUE','SUPPORT_ACCESS_REVIEW_COMPLETED',
        'BREAK_GLASS_ACTIVATED','BREAK_GLASS_ENDED','BREAK_GLASS_EXPIRED',
        'BREAK_GLASS_REVIEW_REQUIRED','BREAK_GLASS_REVIEW_OVERDUE','BREAK_GLASS_REVIEW_COMPLETED'
    ))
SQL),
        ];
    }

    public function down(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_restore_security_notification_types'), 'Restore prior security notification types.', <<<'SQL'
ALTER TABLE account_security_notifications
    DROP CHECK ck_security_notifications_type,
    ADD CONSTRAINT ck_security_notifications_type CHECK (notification_type IN (
        'PASSWORD_RESET_COMPLETED','MFA_ENABLED','MFA_DISABLED','TOTP_AUTHENTICATOR_ADDED',
        'TOTP_AUTHENTICATOR_REMOVED','PASSKEY_ADDED','PASSKEY_REMOVED',
        'RECOVERY_CODES_REGENERATED','RECOVERY_CODE_USED','PASSKEY_SUSPENDED',
        'PLATFORM_ROLE_ASSIGNED','PLATFORM_ROLE_REVOKED','WORKSPACE_ROLE_ASSIGNED','WORKSPACE_ROLE_REVOKED'
    ))
SQL),
            new SqlMigrationStep(new MigrationStepId('002_restore_rate_limit_scopes'), 'Restore prior identity rate-limit scopes.', <<<'SQL'
ALTER TABLE identity_rate_limit_buckets
    DROP CHECK ck_identity_rate_limit_scope,
    ADD CONSTRAINT ck_identity_rate_limit_scope CHECK (scope IN (
        'ACCOUNT_REGISTRATION_EMAIL','ACCOUNT_REGISTRATION_PEER',
        'EMAIL_VERIFICATION_RESEND_EMAIL','EMAIL_VERIFICATION_RESEND_PEER',
        'EMAIL_VERIFICATION_ATTEMPT','EMAIL_VERIFICATION_PEER',
        'PASSWORD_AUTHENTICATION_EMAIL','PASSWORD_AUTHENTICATION_PEER',
        'PASSWORD_RECOVERY_REQUEST_EMAIL','PASSWORD_RECOVERY_REQUEST_PEER',
        'PASSWORD_RECOVERY_ATTEMPT','PASSWORD_RECOVERY_ATTEMPT_PEER',
        'MFA_AUTHENTICATION_ACCOUNT','MFA_AUTHENTICATION_PEER','PASSKEY_AUTHENTICATION_PEER',
        'STEP_UP_ACCOUNT','STEP_UP_PEER','PASSKEY_REGISTRATION_ACCOUNT'
    ))
SQL),
            new SqlMigrationStep(new MigrationStepId('003_restore_step_up_actions'), 'Restore prior step-up actions.', <<<'SQL'
ALTER TABLE account_step_up_grants
    DROP CHECK ck_step_up_grants_action,
    ADD CONSTRAINT ck_step_up_grants_action CHECK (action IN (
        'MFA_ENROLL_TOTP','MFA_REGISTER_PASSKEY','MFA_ENABLE','MFA_DISABLE',
        'MFA_REGENERATE_RECOVERY_CODES','MFA_REVOKE_TOTP','MFA_REVOKE_PASSKEY',
        'AUTHORIZATION_PLATFORM_ROLE_ASSIGN','AUTHORIZATION_PLATFORM_ROLE_REVOKE',
        'AUTHORIZATION_WORKSPACE_ROLE_ASSIGN','AUTHORIZATION_WORKSPACE_ROLE_REVOKE'
    ))
SQL),
        ];
    }

    public function reversible(): bool
    {
        return true;
    }
}
