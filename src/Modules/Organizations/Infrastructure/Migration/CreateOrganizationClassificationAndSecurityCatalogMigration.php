<?php

declare(strict_types=1);

namespace Qmdb\Modules\Organizations\Infrastructure\Migration;

use Qmdb\Modules\People\Infrastructure\Migration\CreatePeopleGuardianshipAndSecurityCatalogMigration;
use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreateOrganizationClassificationAndSecurityCatalogMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260901040100_create_organization_classification_and_security_catalog');
    }
    public function description(): string
    {
        return 'Create fixed Organization classifications and extend governed security constraints.';
    }
    public function dependencies(): array
    {
        return [(new CreatePeopleGuardianshipAndSecurityCatalogMigration())->id()];
    }
    public function up(): array
    {
        return [
        new SqlMigrationStep(new MigrationStepId('001_create_organization_classifications'), 'Create the global governed Organization classification catalog.', <<<'SQL'
CREATE TABLE organization_classifications (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, code VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'ACTIVE', sort_order SMALLINT UNSIGNED NOT NULL, version INT UNSIGNED NOT NULL DEFAULT 1, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, retired_at DATETIME(6) NULL,
 PRIMARY KEY (id), UNIQUE KEY uq_organization_classifications_public_id (public_id), UNIQUE KEY uq_organization_classifications_code (code), KEY ix_organization_classifications_status_sort (status, sort_order, code, id),
 CONSTRAINT ck_organization_classifications_code CHECK (code REGEXP '^[A-Z][A-Z0-9_]{1,47}$'), CONSTRAINT ck_organization_classifications_status CHECK (status IN ('ACTIVE','RETIRED')), CONSTRAINT ck_organization_classifications_sort CHECK (sort_order >= 0), CONSTRAINT ck_organization_classifications_version CHECK (version >= 1), CONSTRAINT ck_organization_classifications_retired CHECK (status <> 'RETIRED' OR retired_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
        new SqlMigrationStep(new MigrationStepId('002_extend_identity_idempotency_operations'), 'Allow Organization and Unit mutation idempotency operations.', <<<'SQL'
ALTER TABLE identity_idempotency_records DROP CHECK ck_identity_idempotency_operation,
 ADD CONSTRAINT ck_identity_idempotency_operation CHECK (operation IN ('ACCOUNT_REGISTRATION','EMAIL_VERIFICATION_RESEND','PASSWORD_RECOVERY_REQUEST','PASSWORD_RECOVERY_RESET','PERSON_PROFILE_CREATE','PERSON_PROFILE_UPDATE','PERSON_ROLE_ACTIVATE','PERSON_ROLE_DEACTIVATE','MEMORIZER_PROGRESS_UPDATE','DEPENDENT_PROFILE_CREATE','DEPENDENT_PROFILE_UPDATE','GUARDIANSHIP_REVOKE','ORGANIZATION_CREATE','ORGANIZATION_UPDATE','ORGANIZATION_RETIRE','ORGANIZATION_UNIT_CREATE','ORGANIZATION_UNIT_UPDATE','ORGANIZATION_UNIT_RETIRE'))
SQL),
        new SqlMigrationStep(new MigrationStepId('003_extend_step_up_actions'), 'Allow Organization and Unit retirement step-up actions.', "ALTER TABLE account_step_up_grants DROP CHECK ck_step_up_grants_action, ADD CONSTRAINT ck_step_up_grants_action CHECK (action IN ('MFA_ENROLL_TOTP','MFA_REGISTER_PASSKEY','MFA_ENABLE','MFA_DISABLE','MFA_REGENERATE_RECOVERY_CODES','MFA_REVOKE_TOTP','MFA_REVOKE_PASSKEY','AUTHORIZATION_PLATFORM_ROLE_ASSIGN','AUTHORIZATION_PLATFORM_ROLE_REVOKE','AUTHORIZATION_WORKSPACE_ROLE_ASSIGN','AUTHORIZATION_WORKSPACE_ROLE_REVOKE','TEMPORARY_PRIVILEGE_APPROVE','TEMPORARY_PRIVILEGE_ACTIVATE','TEMPORARY_PRIVILEGE_REVOKE','SUPPORT_ACCESS_PLATFORM_APPROVE','SUPPORT_ACCESS_WORKSPACE_APPROVE','SUPPORT_ACCESS_ACTIVATE','SUPPORT_ACCESS_REVOKE','SUPPORT_ACCESS_REVIEW','BREAK_GLASS_ACTIVATE','BREAK_GLASS_REVIEW','ACCOUNT_SUSPEND','ACCOUNT_REACTIVATE','PERSON_PROFILE_SENSITIVE_UPDATE','DEPENDENT_PROFILE_CREATE','GUARDIANSHIP_REVOKE','ORGANIZATION_RETIRE','ORGANIZATION_UNIT_RETIRE'))"),
        new SqlMigrationStep(new MigrationStepId('004_extend_rate_limit_scopes'), 'Allow Organization and Unit mutation rate-limit scopes.', "ALTER TABLE identity_rate_limit_buckets DROP CHECK ck_identity_rate_limit_scope, ADD CONSTRAINT ck_identity_rate_limit_scope CHECK (scope IN ('ACCOUNT_REGISTRATION_EMAIL','ACCOUNT_REGISTRATION_PEER','EMAIL_VERIFICATION_RESEND_EMAIL','EMAIL_VERIFICATION_RESEND_PEER','EMAIL_VERIFICATION_ATTEMPT','EMAIL_VERIFICATION_PEER','PASSWORD_AUTHENTICATION_EMAIL','PASSWORD_AUTHENTICATION_PEER','PASSWORD_RECOVERY_REQUEST_EMAIL','PASSWORD_RECOVERY_REQUEST_PEER','PASSWORD_RECOVERY_ATTEMPT','PASSWORD_RECOVERY_ATTEMPT_PEER','MFA_AUTHENTICATION_ACCOUNT','MFA_AUTHENTICATION_PEER','PASSKEY_AUTHENTICATION_PEER','STEP_UP_ACCOUNT','STEP_UP_PEER','PASSKEY_REGISTRATION_ACCOUNT','PRIVILEGED_ACCESS_REQUEST_ACCOUNT','PRIVILEGED_ACCESS_REQUEST_PEER','PRIVILEGED_ACCESS_APPROVAL_ACCOUNT','PRIVILEGED_ACCESS_ACTIVATION_ACCOUNT','BREAK_GLASS_ACTIVATION_ACCOUNT','BREAK_GLASS_ACTIVATION_PEER','ACCOUNT_STATE_OPERATION_ACCOUNT','ACCOUNT_STATE_OPERATION_PEER','PERSON_PROFILE_MUTATION_ACCOUNT','PERSON_PROFILE_MUTATION_PEER','DEPENDENT_PROFILE_CREATION_ACCOUNT','DEPENDENT_PROFILE_CREATION_PEER','ORGANIZATION_MUTATION_ACCOUNT','ORGANIZATION_MUTATION_PEER','ORGANIZATION_UNIT_MUTATION_ACCOUNT','ORGANIZATION_UNIT_MUTATION_PEER'))"),
        new SqlMigrationStep(new MigrationStepId('005_extend_audit_subject_kinds'), 'Allow Organization and Unit audit subjects.', "ALTER TABLE security_audit_events DROP CHECK ck_security_audit_events_subject, ADD CONSTRAINT ck_security_audit_events_subject CHECK (subject_kind IN ('ACCOUNT','SESSION','DEVICE','ROLE_ASSIGNMENT','AUTHENTICATOR','RECOVERY_CODE_SET','PRIVILEGED_ACCESS','WORKSPACE','SECURITY_AUDIT','PERSON','PERSON_ROLE','GUARDIANSHIP','ORGANIZATION','ORGANIZATION_UNIT'))"),
        ];
    }
    public function down(): array
    {
        return [new SqlMigrationStep(new MigrationStepId('001_drop_organization_classifications'), 'Drop the global Organization classifications.', 'DROP TABLE organization_classifications')];
    }
    public function reversible(): bool
    {
        return true;
    }
}
