<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityRecovery\Infrastructure\Migration;

use Qmdb\Modules\IdentityAccess\Infrastructure\Migration\CreateIdentityRateLimitFoundationMigration;
use Qmdb\Modules\IdentitySessions\Infrastructure\Migration\CreateUserSessionsMigration;
use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class ExtendIdentityRecoveryConstraintsMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260826010900_extend_identity_recovery_constraints');
    }

    public function description(): string
    {
        return 'Extend identity constraints for password recovery and reset.';
    }

    public function dependencies(): array
    {
        return [
            (new CreateIdentityRateLimitFoundationMigration())->id(),
            (new CreateUserSessionsMigration())->id(),
        ];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(
                new MigrationStepId('001_extend_identity_idempotency_operations'),
                'Add password recovery idempotency operations.',
                <<<'SQL'
ALTER TABLE identity_idempotency_records
    DROP CHECK ck_identity_idempotency_operation,
    ADD CONSTRAINT ck_identity_idempotency_operation CHECK (
        operation IN (
            'ACCOUNT_REGISTRATION','EMAIL_VERIFICATION_RESEND',
            'PASSWORD_RECOVERY_REQUEST','PASSWORD_RECOVERY_RESET'
        )
    )
SQL,
            ),
            new SqlMigrationStep(
                new MigrationStepId('002_extend_identity_rate_limit_scopes'),
                'Add password recovery rate-limit scopes.',
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
                new MigrationStepId('003_extend_session_revoke_reasons'),
                'Add password-reset session revoke reason.',
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
                new MigrationStepId('004_add_account_email_candidate_key'),
                'Add the account-aware email candidate key.',
                'ALTER TABLE account_email_addresses '
                . 'ADD UNIQUE KEY uq_account_email_addresses_account_id (user_account_id, id)',
            ),
        ];
    }

    public function down(): array
    {
        return [
            new SqlMigrationStep(
                new MigrationStepId('001_drop_account_email_candidate_key'),
                'Drop the account-aware email candidate key.',
                'ALTER TABLE account_email_addresses DROP INDEX uq_account_email_addresses_account_id',
            ),
            new SqlMigrationStep(
                new MigrationStepId('002_restore_session_revoke_reasons'),
                'Restore pre-recovery session revoke reasons.',
                <<<'SQL'
ALTER TABLE user_sessions
    DROP CHECK ck_user_sessions_revoke_reason,
    ADD CONSTRAINT ck_user_sessions_revoke_reason CHECK (
        revoke_reason_code IS NULL OR revoke_reason_code IN (
            'USER_LOGOUT','REMOTE_SESSION_REVOCATION','DEVICE_REVOCATION','SESSION_LIMIT',
            'REAUTHENTICATION','ACCOUNT_NOT_ACTIVE','DEVICE_NOT_ACTIVE','TOKEN_COMPROMISE'
        )
    )
SQL,
            ),
            new SqlMigrationStep(
                new MigrationStepId('003_restore_identity_rate_limit_scopes'),
                'Restore pre-recovery rate-limit scopes.',
                <<<'SQL'
ALTER TABLE identity_rate_limit_buckets
    DROP CHECK ck_identity_rate_limit_scope,
    ADD CONSTRAINT ck_identity_rate_limit_scope CHECK (scope IN (
        'ACCOUNT_REGISTRATION_EMAIL','ACCOUNT_REGISTRATION_PEER',
        'EMAIL_VERIFICATION_RESEND_EMAIL','EMAIL_VERIFICATION_RESEND_PEER',
        'EMAIL_VERIFICATION_ATTEMPT','EMAIL_VERIFICATION_PEER',
        'PASSWORD_AUTHENTICATION_EMAIL','PASSWORD_AUTHENTICATION_PEER'
    ))
SQL,
            ),
            new SqlMigrationStep(
                new MigrationStepId('004_restore_identity_idempotency_operations'),
                'Restore pre-recovery idempotency operations.',
                <<<'SQL'
ALTER TABLE identity_idempotency_records
    DROP CHECK ck_identity_idempotency_operation,
    ADD CONSTRAINT ck_identity_idempotency_operation CHECK (
        operation IN ('ACCOUNT_REGISTRATION','EMAIL_VERIFICATION_RESEND')
    )
SQL,
            ),
        ];
    }

    public function reversible(): bool
    {
        return true;
    }
}
