<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Infrastructure\Migration;

use Qmdb\Modules\IdentitySecurityNotifications\Infrastructure\Migration\CreateSecurityNotificationFoundationMigration;
use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class ExtendIdentityMultiFactorConstraintsMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260826011200_extend_identity_multifactor_constraints');
    }

    public function description(): string
    {
        return 'Extend session assurance and identity security constraints.';
    }

    public function dependencies(): array
    {
        return [(new CreateSecurityNotificationFoundationMigration())->id()];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(
                new MigrationStepId('001_add_session_assurance'),
                'Add authentication assurance to user sessions.',
                <<<'SQL'
ALTER TABLE user_sessions
    ADD COLUMN primary_authentication_method VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin
        NOT NULL DEFAULT 'PASSWORD' AFTER authenticated_at,
    ADD COLUMN secondary_authentication_method VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin
        NULL AFTER primary_authentication_method,
    ADD COLUMN assurance_level VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin
        NOT NULL DEFAULT 'PRIMARY' AFTER secondary_authentication_method,
    ADD COLUMN strong_authenticated_at DATETIME(6) NULL AFTER assurance_level,
    ADD CONSTRAINT ck_user_sessions_primary_method
        CHECK (primary_authentication_method IN ('PASSWORD','PASSKEY')),
    ADD CONSTRAINT ck_user_sessions_secondary_method
        CHECK (secondary_authentication_method IS NULL
            OR secondary_authentication_method IN ('TOTP','RECOVERY_CODE','PASSKEY')),
    ADD CONSTRAINT ck_user_sessions_assurance_level
        CHECK (assurance_level IN ('PRIMARY','MULTI_FACTOR','PHISHING_RESISTANT')),
    ADD CONSTRAINT ck_user_sessions_assurance_consistency CHECK (
        (assurance_level = 'PRIMARY' AND secondary_authentication_method IS NULL
            AND strong_authenticated_at IS NULL)
        OR (assurance_level = 'MULTI_FACTOR'
            AND secondary_authentication_method IN ('TOTP','RECOVERY_CODE')
            AND strong_authenticated_at IS NOT NULL)
        OR (assurance_level = 'PHISHING_RESISTANT'
            AND (primary_authentication_method = 'PASSKEY' OR secondary_authentication_method = 'PASSKEY')
            AND strong_authenticated_at IS NOT NULL)
    )
SQL,
            ),
            new SqlMigrationStep(
                new MigrationStepId('002_extend_identity_rate_limits'),
                'Add MFA, passkey and step-up rate-limit scopes.',
                <<<'SQL'
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
SQL,
            ),
            new SqlMigrationStep(
                new MigrationStepId('003_extend_session_revoke_reasons'),
                'Add MFA session revocation reasons.',
                <<<'SQL'
ALTER TABLE user_sessions
    DROP CHECK ck_user_sessions_revoke_reason,
    ADD CONSTRAINT ck_user_sessions_revoke_reason CHECK (
        revoke_reason_code IS NULL OR revoke_reason_code IN (
            'USER_LOGOUT','REMOTE_SESSION_REVOCATION','DEVICE_REVOCATION','SESSION_LIMIT',
            'REAUTHENTICATION','ACCOUNT_NOT_ACTIVE','DEVICE_NOT_ACTIVE','TOKEN_COMPROMISE','PASSWORD_RESET',
            'MFA_POLICY_CHANGED','AUTHENTICATOR_COMPROMISE'
        )
    )
SQL,
            ),
            new SqlMigrationStep(
                new MigrationStepId('004_extend_security_notification_types'),
                'Add MFA and passkey security-notification types.',
                <<<'SQL'
ALTER TABLE account_security_notifications
    DROP CHECK ck_security_notifications_type,
    ADD CONSTRAINT ck_security_notifications_type CHECK (notification_type IN (
        'PASSWORD_RESET_COMPLETED','MFA_ENABLED','MFA_DISABLED','TOTP_AUTHENTICATOR_ADDED',
        'TOTP_AUTHENTICATOR_REMOVED','PASSKEY_ADDED','PASSKEY_REMOVED',
        'RECOVERY_CODES_REGENERATED','RECOVERY_CODE_USED','PASSKEY_SUSPENDED'
    ))
SQL,
            ),
        ];
    }

    public function down(): array
    {
        return [
            new SqlMigrationStep(
                new MigrationStepId('001_restore_security_notification_types'),
                'Restore pre-MFA security-notification types.',
                "ALTER TABLE account_security_notifications DROP CHECK ck_security_notifications_type, "
                . "ADD CONSTRAINT ck_security_notifications_type CHECK (notification_type IN "
                . "('PASSWORD_RESET_COMPLETED'))",
            ),
            new SqlMigrationStep(
                new MigrationStepId('002_restore_session_revoke_reasons'),
                'Restore pre-MFA session revocation reasons.',
                <<<'SQL'
ALTER TABLE user_sessions
    DROP CHECK ck_user_sessions_revoke_reason,
    ADD CONSTRAINT ck_user_sessions_revoke_reason CHECK (
        revoke_reason_code IS NULL OR revoke_reason_code IN (
            'USER_LOGOUT','REMOTE_SESSION_REVOCATION','DEVICE_REVOCATION','SESSION_LIMIT',
            'REAUTHENTICATION','ACCOUNT_NOT_ACTIVE','DEVICE_NOT_ACTIVE','TOKEN_COMPROMISE','PASSWORD_RESET'
        )
    )
SQL,
            ),
            new SqlMigrationStep(
                new MigrationStepId('003_restore_identity_rate_limits'),
                'Restore pre-MFA identity rate-limit scopes.',
                <<<'SQL'
ALTER TABLE identity_rate_limit_buckets
    DROP CHECK ck_identity_rate_limit_scope,
    ADD CONSTRAINT ck_identity_rate_limit_scope CHECK (scope IN (
        'ACCOUNT_REGISTRATION_EMAIL','ACCOUNT_REGISTRATION_PEER',
        'EMAIL_VERIFICATION_RESEND_EMAIL','EMAIL_VERIFICATION_RESEND_PEER',
        'EMAIL_VERIFICATION_ATTEMPT','EMAIL_VERIFICATION_PEER',
        'PASSWORD_AUTHENTICATION_EMAIL','PASSWORD_AUTHENTICATION_PEER',
        'PASSWORD_RECOVERY_REQUEST_EMAIL','PASSWORD_RECOVERY_REQUEST_PEER',
        'PASSWORD_RECOVERY_ATTEMPT','PASSWORD_RECOVERY_ATTEMPT_PEER'
    ))
SQL,
            ),
            new SqlMigrationStep(
                new MigrationStepId('004_drop_session_assurance'),
                'Drop session authentication assurance.',
                <<<'SQL'
ALTER TABLE user_sessions
    DROP CHECK ck_user_sessions_assurance_consistency,
    DROP CHECK ck_user_sessions_assurance_level,
    DROP CHECK ck_user_sessions_secondary_method,
    DROP CHECK ck_user_sessions_primary_method,
    DROP COLUMN strong_authenticated_at,
    DROP COLUMN assurance_level,
    DROP COLUMN secondary_authentication_method,
    DROP COLUMN primary_authentication_method
SQL,
            ),
        ];
    }

    public function reversible(): bool
    {
        return true;
    }
}
