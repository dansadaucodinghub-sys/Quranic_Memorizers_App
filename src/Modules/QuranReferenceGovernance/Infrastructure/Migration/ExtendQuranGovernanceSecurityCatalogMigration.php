<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class ExtendQuranGovernanceSecurityCatalogMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260910060200_extend_quran_governance_security_catalog');
    }
    public function description(): string
    {
        return 'Extend fixed security catalogs for Qur’an release governance.';
    }
    public function dependencies(): array
    {
        return [(new CreateQuranReferenceGovernanceMigration())->id()];
    }
    public function up(): array
    {
        return [
        new SqlMigrationStep(new MigrationStepId('001_extend_step_up'), 'Allow exact Qur’an release step-up actions.', "ALTER TABLE account_step_up_grants DROP CHECK ck_step_up_grants_action, ADD CONSTRAINT ck_step_up_grants_action CHECK (action IN ('MFA_ENROLL_TOTP','MFA_REGISTER_PASSKEY','MFA_ENABLE','MFA_DISABLE','MFA_REGENERATE_RECOVERY_CODES','MFA_REVOKE_TOTP','MFA_REVOKE_PASSKEY','AUTHORIZATION_PLATFORM_ROLE_ASSIGN','AUTHORIZATION_PLATFORM_ROLE_REVOKE','AUTHORIZATION_WORKSPACE_ROLE_ASSIGN','AUTHORIZATION_WORKSPACE_ROLE_REVOKE','TEMPORARY_PRIVILEGE_APPROVE','TEMPORARY_PRIVILEGE_ACTIVATE','TEMPORARY_PRIVILEGE_REVOKE','SUPPORT_ACCESS_PLATFORM_APPROVE','SUPPORT_ACCESS_WORKSPACE_APPROVE','SUPPORT_ACCESS_ACTIVATE','SUPPORT_ACCESS_REVOKE','SUPPORT_ACCESS_REVIEW','BREAK_GLASS_ACTIVATE','BREAK_GLASS_REVIEW','ACCOUNT_SUSPEND','ACCOUNT_REACTIVATE','PERSON_PROFILE_SENSITIVE_UPDATE','DEPENDENT_PROFILE_CREATE','GUARDIANSHIP_REVOKE','ORGANIZATION_RETIRE','ORGANIZATION_UNIT_RETIRE','QURAN_RELEASE_APPROVE','QURAN_RELEASE_ACTIVATE','QURAN_RELEASE_REJECT'))"),
        new SqlMigrationStep(new MigrationStepId('002_extend_rate_limits'), 'Allow HMAC-backed Qur’an governance rate-limit scopes.', "ALTER TABLE identity_rate_limit_buckets DROP CHECK ck_identity_rate_limit_scope, ADD CONSTRAINT ck_identity_rate_limit_scope CHECK (scope IN ('ACCOUNT_REGISTRATION_EMAIL','ACCOUNT_REGISTRATION_PEER','EMAIL_VERIFICATION_RESEND_EMAIL','EMAIL_VERIFICATION_RESEND_PEER','EMAIL_VERIFICATION_ATTEMPT','EMAIL_VERIFICATION_PEER','PASSWORD_AUTHENTICATION_EMAIL','PASSWORD_AUTHENTICATION_PEER','PASSWORD_RECOVERY_REQUEST_EMAIL','PASSWORD_RECOVERY_REQUEST_PEER','PASSWORD_RECOVERY_ATTEMPT','PASSWORD_RECOVERY_ATTEMPT_PEER','MFA_AUTHENTICATION_ACCOUNT','MFA_AUTHENTICATION_PEER','PASSKEY_AUTHENTICATION_PEER','STEP_UP_ACCOUNT','STEP_UP_PEER','PASSKEY_REGISTRATION_ACCOUNT','QURAN_GOVERNANCE_MUTATION_ACCOUNT','QURAN_GOVERNANCE_MUTATION_PEER'))"),
        new SqlMigrationStep(new MigrationStepId('003_extend_audit_subjects'), 'Allow Qur’an source and release audit subjects.', "ALTER TABLE security_audit_events DROP CHECK ck_security_audit_events_subject, ADD CONSTRAINT ck_security_audit_events_subject CHECK (subject_kind IN ('ACCOUNT','SESSION','DEVICE','ROLE_ASSIGNMENT','AUTHENTICATOR','RECOVERY_CODE_SET','PRIVILEGED_ACCESS','WORKSPACE','SECURITY_AUDIT','PERSON','PERSON_ROLE','GUARDIANSHIP','ORGANIZATION','ORGANIZATION_UNIT','ORGANIZATION_AFFILIATION','ORGANIZATION_AFFILIATION_ASSIGNMENT','PROFILE_CLAIM','PROFILE_VERIFICATION','PERSON_DUPLICATE_CASE','PERSON_ALIAS','QURAN_SOURCE','QURAN_RELEASE'))"),
        ];
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
