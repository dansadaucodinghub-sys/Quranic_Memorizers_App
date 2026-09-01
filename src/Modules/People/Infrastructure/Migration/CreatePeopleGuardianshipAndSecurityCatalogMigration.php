<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreatePeopleGuardianshipAndSecurityCatalogMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260901030400_create_people_guardianship_and_security_catalog');
    }

    public function description(): string
    {
        return 'Create guardianships and extend closed P2 security catalogs for private Person-profile operations.';
    }

    public function dependencies(): array
    {
        return [(new CreatePeopleRoleProfilesMigration())->id(), new MigrationId('20260826012700_preserve_canonical_audit_metadata')];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_create_people_guardianships'), 'Create active and historical guardian authority records.', <<<'SQL'
CREATE TABLE people_guardianships (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id BINARY(16) NOT NULL,
    guardian_person_id BIGINT UNSIGNED NOT NULL,
    dependent_person_id BIGINT UNSIGNED NOT NULL,
    relationship_type VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    authority_scope VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    authority_basis VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_by_account_id BIGINT UNSIGNED NOT NULL,
    revoked_by_account_id BIGINT UNSIGNED NULL,
    confirmed_at DATETIME(6) NULL,
    revoked_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    active_relationship_marker TINYINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status = 'ACTIVE' THEN 1 ELSE NULL END) STORED,
    PRIMARY KEY (id),
    UNIQUE KEY uq_people_guardianships_public_id (public_id),
    UNIQUE KEY uq_people_guardianships_active_pair (guardian_person_id, dependent_person_id, authority_scope, active_relationship_marker),
    KEY ix_people_guardianships_guardian_status (guardian_person_id, status, created_at, id),
    KEY ix_people_guardianships_dependent_status (dependent_person_id, status, created_at, id),
    KEY ix_people_guardianships_status_updated (status, updated_at, id),
    CONSTRAINT fk_people_guardianships_guardian FOREIGN KEY (guardian_person_id)
        REFERENCES people_persons (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_people_guardianships_dependent FOREIGN KEY (dependent_person_id)
        REFERENCES people_persons (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_people_guardianships_created_by_account FOREIGN KEY (created_by_account_id)
        REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_people_guardianships_revoked_by_account FOREIGN KEY (revoked_by_account_id)
        REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_people_guardianships_distinct CHECK (guardian_person_id <> dependent_person_id),
    CONSTRAINT ck_people_guardianships_relationship CHECK (relationship_type IN ('PARENT','LEGAL_GUARDIAN','CAREGIVER','OTHER')),
    CONSTRAINT ck_people_guardianships_scope CHECK (authority_scope = 'PROFILE_MANAGEMENT'),
    CONSTRAINT ck_people_guardianships_basis CHECK (authority_basis = 'SELF_DECLARED'),
    CONSTRAINT ck_people_guardianships_status CHECK (status IN ('ACTIVE','REVOKED')),
    CONSTRAINT ck_people_guardianships_version CHECK (version >= 1),
    CONSTRAINT ck_people_guardianships_active_confirmed CHECK (status <> 'ACTIVE' OR confirmed_at IS NOT NULL),
    CONSTRAINT ck_people_guardianships_revoked CHECK (status <> 'REVOKED' OR (revoked_at IS NOT NULL AND revoked_by_account_id IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('002_create_people_profile_operation_results'), 'Create private Person-profile idempotency result references.', <<<'SQL'
CREATE TABLE people_profile_operation_results (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    idempotency_public_id BINARY(16) NOT NULL,
    operation VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    person_id BIGINT UNSIGNED NULL,
    role_profile_id BIGINT UNSIGNED NULL,
    guardianship_id BIGINT UNSIGNED NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_people_profile_operation_results_submission (idempotency_public_id),
    KEY ix_people_profile_operation_results_person (person_id, created_at, id),
    CONSTRAINT fk_people_profile_operation_results_person FOREIGN KEY (person_id)
        REFERENCES people_persons (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_people_profile_operation_results_role FOREIGN KEY (role_profile_id)
        REFERENCES people_role_profiles (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_people_profile_operation_results_guardianship FOREIGN KEY (guardianship_id)
        REFERENCES people_guardianships (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT ck_people_profile_operation_results_target CHECK (
        person_id IS NOT NULL OR role_profile_id IS NOT NULL OR guardianship_id IS NOT NULL
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('003_extend_identity_idempotency_operations'), 'Allow private Person-profile idempotency operations.', <<<'SQL'
ALTER TABLE identity_idempotency_records
    DROP CHECK ck_identity_idempotency_operation,
    ADD CONSTRAINT ck_identity_idempotency_operation CHECK (operation IN (
        'ACCOUNT_REGISTRATION','EMAIL_VERIFICATION_RESEND','PASSWORD_RECOVERY_REQUEST','PASSWORD_RECOVERY_RESET',
        'PERSON_PROFILE_CREATE','PERSON_PROFILE_UPDATE','PERSON_ROLE_ACTIVATE','PERSON_ROLE_DEACTIVATE',
        'MEMORIZER_PROGRESS_UPDATE','DEPENDENT_PROFILE_CREATE','DEPENDENT_PROFILE_UPDATE','GUARDIANSHIP_REVOKE'
    ))
SQL),
            new SqlMigrationStep(new MigrationStepId('004_extend_step_up_actions'), 'Allow Person-profile step-up actions.', <<<'SQL'
ALTER TABLE account_step_up_grants
    DROP CHECK ck_step_up_grants_action,
    ADD CONSTRAINT ck_step_up_grants_action CHECK (action IN (
        'MFA_ENROLL_TOTP','MFA_REGISTER_PASSKEY','MFA_ENABLE','MFA_DISABLE','MFA_REGENERATE_RECOVERY_CODES',
        'MFA_REVOKE_TOTP','MFA_REVOKE_PASSKEY','AUTHORIZATION_PLATFORM_ROLE_ASSIGN',
        'AUTHORIZATION_PLATFORM_ROLE_REVOKE','AUTHORIZATION_WORKSPACE_ROLE_ASSIGN',
        'AUTHORIZATION_WORKSPACE_ROLE_REVOKE','TEMPORARY_PRIVILEGE_APPROVE','TEMPORARY_PRIVILEGE_ACTIVATE',
        'TEMPORARY_PRIVILEGE_REVOKE','SUPPORT_ACCESS_PLATFORM_APPROVE','SUPPORT_ACCESS_WORKSPACE_APPROVE',
        'SUPPORT_ACCESS_ACTIVATE','SUPPORT_ACCESS_REVOKE','SUPPORT_ACCESS_REVIEW','BREAK_GLASS_ACTIVATE',
        'BREAK_GLASS_REVIEW','ACCOUNT_SUSPEND','ACCOUNT_REACTIVATE','PERSON_PROFILE_SENSITIVE_UPDATE',
        'DEPENDENT_PROFILE_CREATE','GUARDIANSHIP_REVOKE'
    ))
SQL),
            new SqlMigrationStep(new MigrationStepId('005_extend_rate_limit_scopes'), 'Allow Person-profile mutation rate-limit scopes.', <<<'SQL'
ALTER TABLE identity_rate_limit_buckets
    DROP CHECK ck_identity_rate_limit_scope,
    ADD CONSTRAINT ck_identity_rate_limit_scope CHECK (scope IN (
        'ACCOUNT_REGISTRATION_EMAIL','ACCOUNT_REGISTRATION_PEER','EMAIL_VERIFICATION_RESEND_EMAIL',
        'EMAIL_VERIFICATION_RESEND_PEER','EMAIL_VERIFICATION_ATTEMPT','EMAIL_VERIFICATION_PEER',
        'PASSWORD_AUTHENTICATION_EMAIL','PASSWORD_AUTHENTICATION_PEER','PASSWORD_RECOVERY_REQUEST_EMAIL',
        'PASSWORD_RECOVERY_REQUEST_PEER','PASSWORD_RECOVERY_ATTEMPT','PASSWORD_RECOVERY_ATTEMPT_PEER',
        'MFA_AUTHENTICATION_ACCOUNT','MFA_AUTHENTICATION_PEER','PASSKEY_AUTHENTICATION_PEER','STEP_UP_ACCOUNT',
        'STEP_UP_PEER','PASSKEY_REGISTRATION_ACCOUNT','PRIVILEGED_ACCESS_REQUEST_ACCOUNT',
        'PRIVILEGED_ACCESS_REQUEST_PEER','PRIVILEGED_ACCESS_APPROVAL_ACCOUNT',
        'PRIVILEGED_ACCESS_ACTIVATION_ACCOUNT','BREAK_GLASS_ACTIVATION_ACCOUNT','BREAK_GLASS_ACTIVATION_PEER',
        'ACCOUNT_STATE_OPERATION_ACCOUNT','ACCOUNT_STATE_OPERATION_PEER','PERSON_PROFILE_MUTATION_ACCOUNT',
        'PERSON_PROFILE_MUTATION_PEER','DEPENDENT_PROFILE_CREATION_ACCOUNT','DEPENDENT_PROFILE_CREATION_PEER'
    ))
SQL),
            new SqlMigrationStep(new MigrationStepId('006_extend_notification_types'), 'Allow private Person-profile security notifications.', <<<'SQL'
ALTER TABLE account_security_notifications
    DROP CHECK ck_security_notifications_type,
    ADD CONSTRAINT ck_security_notifications_type CHECK (notification_type IN (
        'PASSWORD_RESET_COMPLETED','MFA_ENABLED','MFA_DISABLED','TOTP_AUTHENTICATOR_ADDED',
        'TOTP_AUTHENTICATOR_REMOVED','PASSKEY_ADDED','PASSKEY_REMOVED','RECOVERY_CODES_REGENERATED',
        'RECOVERY_CODE_USED','PASSKEY_SUSPENDED','PLATFORM_ROLE_ASSIGNED','PLATFORM_ROLE_REVOKED',
        'WORKSPACE_ROLE_ASSIGNED','WORKSPACE_ROLE_REVOKED','TEMPORARY_PRIVILEGE_REQUESTED',
        'TEMPORARY_PRIVILEGE_APPROVED','TEMPORARY_PRIVILEGE_REJECTED','TEMPORARY_PRIVILEGE_ACTIVATED',
        'TEMPORARY_PRIVILEGE_REVOKED','TEMPORARY_PRIVILEGE_EXPIRED','SUPPORT_ACCESS_REQUESTED',
        'SUPPORT_ACCESS_PARTIALLY_APPROVED','SUPPORT_ACCESS_APPROVED','SUPPORT_ACCESS_ACTIVATED',
        'SUPPORT_ACCESS_ENDED','SUPPORT_ACCESS_REVOKED','SUPPORT_ACCESS_REVIEW_REQUIRED',
        'SUPPORT_ACCESS_REVIEW_OVERDUE','SUPPORT_ACCESS_REVIEW_COMPLETED','BREAK_GLASS_ACTIVATED',
        'BREAK_GLASS_ENDED','BREAK_GLASS_EXPIRED','BREAK_GLASS_REVIEW_REQUIRED',
        'BREAK_GLASS_REVIEW_OVERDUE','BREAK_GLASS_REVIEW_COMPLETED','ACCOUNT_SUSPENDED','ACCOUNT_REACTIVATED',
        'PERSON_PROFILE_CREATED','PERSON_PROFILE_UPDATED','DEPENDENT_PROFILE_CREATED','GUARDIANSHIP_REVOKED'
    ))
SQL),
            new SqlMigrationStep(new MigrationStepId('007_extend_audit_subject_kinds'), 'Allow Person and guardianship audit subjects.', <<<'SQL'
ALTER TABLE security_audit_events
    DROP CHECK ck_security_audit_events_subject,
    ADD CONSTRAINT ck_security_audit_events_subject CHECK (subject_kind IN (
        'ACCOUNT','SESSION','DEVICE','ROLE_ASSIGNMENT','AUTHENTICATOR','RECOVERY_CODE_SET',
        'PRIVILEGED_ACCESS','WORKSPACE','SECURITY_AUDIT','PERSON','PERSON_ROLE','GUARDIANSHIP'
    ))
SQL),
        ];
    }

    public function down(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_restore_audit_subject_kinds'), 'Restore pre-P3 audit subject kinds.', "ALTER TABLE security_audit_events DROP CHECK ck_security_audit_events_subject, ADD CONSTRAINT ck_security_audit_events_subject CHECK (subject_kind IN ('ACCOUNT','SESSION','DEVICE','ROLE_ASSIGNMENT','AUTHENTICATOR','RECOVERY_CODE_SET','PRIVILEGED_ACCESS','WORKSPACE','SECURITY_AUDIT'))"),
            new SqlMigrationStep(new MigrationStepId('002_restore_notification_types'), 'Restore P2 notification catalog.', "ALTER TABLE account_security_notifications DROP CHECK ck_security_notifications_type, ADD CONSTRAINT ck_security_notifications_type CHECK (notification_type IN ('PASSWORD_RESET_COMPLETED','MFA_ENABLED','MFA_DISABLED','TOTP_AUTHENTICATOR_ADDED','TOTP_AUTHENTICATOR_REMOVED','PASSKEY_ADDED','PASSKEY_REMOVED','RECOVERY_CODES_REGENERATED','RECOVERY_CODE_USED','PASSKEY_SUSPENDED','PLATFORM_ROLE_ASSIGNED','PLATFORM_ROLE_REVOKED','WORKSPACE_ROLE_ASSIGNED','WORKSPACE_ROLE_REVOKED','TEMPORARY_PRIVILEGE_REQUESTED','TEMPORARY_PRIVILEGE_APPROVED','TEMPORARY_PRIVILEGE_REJECTED','TEMPORARY_PRIVILEGE_ACTIVATED','TEMPORARY_PRIVILEGE_REVOKED','TEMPORARY_PRIVILEGE_EXPIRED','SUPPORT_ACCESS_REQUESTED','SUPPORT_ACCESS_PARTIALLY_APPROVED','SUPPORT_ACCESS_APPROVED','SUPPORT_ACCESS_ACTIVATED','SUPPORT_ACCESS_ENDED','SUPPORT_ACCESS_REVOKED','SUPPORT_ACCESS_REVIEW_REQUIRED','SUPPORT_ACCESS_REVIEW_OVERDUE','SUPPORT_ACCESS_REVIEW_COMPLETED','BREAK_GLASS_ACTIVATED','BREAK_GLASS_ENDED','BREAK_GLASS_EXPIRED','BREAK_GLASS_REVIEW_REQUIRED','BREAK_GLASS_REVIEW_OVERDUE','BREAK_GLASS_REVIEW_COMPLETED','ACCOUNT_SUSPENDED','ACCOUNT_REACTIVATED'))"),
            new SqlMigrationStep(new MigrationStepId('003_restore_rate_limit_scopes'), 'Restore P2 rate-limit catalog.', "ALTER TABLE identity_rate_limit_buckets DROP CHECK ck_identity_rate_limit_scope, ADD CONSTRAINT ck_identity_rate_limit_scope CHECK (scope IN ('ACCOUNT_REGISTRATION_EMAIL','ACCOUNT_REGISTRATION_PEER','EMAIL_VERIFICATION_RESEND_EMAIL','EMAIL_VERIFICATION_RESEND_PEER','EMAIL_VERIFICATION_ATTEMPT','EMAIL_VERIFICATION_PEER','PASSWORD_AUTHENTICATION_EMAIL','PASSWORD_AUTHENTICATION_PEER','PASSWORD_RECOVERY_REQUEST_EMAIL','PASSWORD_RECOVERY_REQUEST_PEER','PASSWORD_RECOVERY_ATTEMPT','PASSWORD_RECOVERY_ATTEMPT_PEER','MFA_AUTHENTICATION_ACCOUNT','MFA_AUTHENTICATION_PEER','PASSKEY_AUTHENTICATION_PEER','STEP_UP_ACCOUNT','STEP_UP_PEER','PASSKEY_REGISTRATION_ACCOUNT','PRIVILEGED_ACCESS_REQUEST_ACCOUNT','PRIVILEGED_ACCESS_REQUEST_PEER','PRIVILEGED_ACCESS_APPROVAL_ACCOUNT','PRIVILEGED_ACCESS_ACTIVATION_ACCOUNT','BREAK_GLASS_ACTIVATION_ACCOUNT','BREAK_GLASS_ACTIVATION_PEER','ACCOUNT_STATE_OPERATION_ACCOUNT','ACCOUNT_STATE_OPERATION_PEER'))"),
            new SqlMigrationStep(new MigrationStepId('004_restore_step_up_actions'), 'Restore P2 step-up action catalog.', "ALTER TABLE account_step_up_grants DROP CHECK ck_step_up_grants_action, ADD CONSTRAINT ck_step_up_grants_action CHECK (action IN ('MFA_ENROLL_TOTP','MFA_REGISTER_PASSKEY','MFA_ENABLE','MFA_DISABLE','MFA_REGENERATE_RECOVERY_CODES','MFA_REVOKE_TOTP','MFA_REVOKE_PASSKEY','AUTHORIZATION_PLATFORM_ROLE_ASSIGN','AUTHORIZATION_PLATFORM_ROLE_REVOKE','AUTHORIZATION_WORKSPACE_ROLE_ASSIGN','AUTHORIZATION_WORKSPACE_ROLE_REVOKE','TEMPORARY_PRIVILEGE_APPROVE','TEMPORARY_PRIVILEGE_ACTIVATE','TEMPORARY_PRIVILEGE_REVOKE','SUPPORT_ACCESS_PLATFORM_APPROVE','SUPPORT_ACCESS_WORKSPACE_APPROVE','SUPPORT_ACCESS_ACTIVATE','SUPPORT_ACCESS_REVOKE','SUPPORT_ACCESS_REVIEW','BREAK_GLASS_ACTIVATE','BREAK_GLASS_REVIEW','ACCOUNT_SUSPEND','ACCOUNT_REACTIVATE'))"),
            new SqlMigrationStep(new MigrationStepId('005_restore_idempotency_operations'), 'Restore P2 idempotency catalog.', "ALTER TABLE identity_idempotency_records DROP CHECK ck_identity_idempotency_operation, ADD CONSTRAINT ck_identity_idempotency_operation CHECK (operation IN ('ACCOUNT_REGISTRATION','EMAIL_VERIFICATION_RESEND','PASSWORD_RECOVERY_REQUEST','PASSWORD_RECOVERY_RESET'))"),
            new SqlMigrationStep(new MigrationStepId('006_drop_people_profile_operation_results'), 'Drop Person-profile operation results.', 'DROP TABLE people_profile_operation_results'),
            new SqlMigrationStep(new MigrationStepId('007_drop_people_guardianships'), 'Drop guardianships.', 'DROP TABLE people_guardianships'),
        ];
    }

    public function reversible(): bool
    {
        return true;
    }
}
