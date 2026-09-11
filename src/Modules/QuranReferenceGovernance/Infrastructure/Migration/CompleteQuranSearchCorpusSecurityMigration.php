<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/**
 * Repairs prior additive-catalog migrations by preserving every established
 * value while adding only the B03 corpus-validation values.
 */
final readonly class CompleteQuranSearchCorpusSecurityMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260911110000_complete_quran_search_corpus_security');
    }
    public function description(): string
    {
        return 'Preserve closed security catalogs and add private Qur’an search-corpus validation controls.';
    }
    public function dependencies(): array
    {
        return [(new ExtendQuranPublicSearchRateLimitMigration())->id()];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_step_up_actions'), 'Preserve all exact step-up actions and add corpus validation.', "ALTER TABLE account_step_up_grants DROP CHECK ck_step_up_grants_action, ADD CONSTRAINT ck_step_up_grants_action CHECK (action IN ('MFA_ENROLL_TOTP','MFA_REGISTER_PASSKEY','MFA_ENABLE','MFA_DISABLE','MFA_REGENERATE_RECOVERY_CODES','MFA_REVOKE_TOTP','MFA_REVOKE_PASSKEY','AUTHORIZATION_PLATFORM_ROLE_ASSIGN','AUTHORIZATION_PLATFORM_ROLE_REVOKE','AUTHORIZATION_WORKSPACE_ROLE_ASSIGN','AUTHORIZATION_WORKSPACE_ROLE_REVOKE','TEMPORARY_PRIVILEGE_APPROVE','TEMPORARY_PRIVILEGE_ACTIVATE','TEMPORARY_PRIVILEGE_REVOKE','SUPPORT_ACCESS_PLATFORM_APPROVE','SUPPORT_ACCESS_WORKSPACE_APPROVE','SUPPORT_ACCESS_ACTIVATE','SUPPORT_ACCESS_REVOKE','SUPPORT_ACCESS_REVIEW','BREAK_GLASS_ACTIVATE','BREAK_GLASS_REVIEW','ACCOUNT_SUSPEND','ACCOUNT_REACTIVATE','PERSON_PROFILE_SENSITIVE_UPDATE','DEPENDENT_PROFILE_CREATE','GUARDIANSHIP_REVOKE','ORGANIZATION_RETIRE','ORGANIZATION_UNIT_RETIRE','ORGANIZATION_AFFILIATION_ACCEPT','ORGANIZATION_AFFILIATION_ACCEPT_LEADERSHIP','ORGANIZATION_AFFILIATION_SUSPEND','ORGANIZATION_AFFILIATION_RESUME','ORGANIZATION_AFFILIATION_END','ORGANIZATION_AFFILIATION_LEAVE','ORGANIZATION_LEADERSHIP_ASSIGN','ORGANIZATION_LEADERSHIP_REMOVE','PROFILE_CLAIM_AUTHORIZE_GUARDIAN','PROFILE_CLAIM_AUTHORIZE_PLATFORM','PROFILE_CLAIM_ACCEPT','PROFILE_CLAIM_REVOKE','PROFILE_VERIFICATION_RECORD','PROFILE_VERIFICATION_REVOKE','PROFILE_DUPLICATE_REPORT','PROFILE_DUPLICATE_CONSENT','PROFILE_DUPLICATE_DISMISS','PROFILE_DUPLICATE_RESOLVE','QURAN_RELEASE_APPROVE','QURAN_RELEASE_ACTIVATE','QURAN_RELEASE_REJECT','QURAN_SEARCH_CORPUS_VALIDATE'))"),
            new SqlMigrationStep(new MigrationStepId('002_rate_limit_scopes'), 'Preserve all exact rate-limit scopes and add public query protection.', "ALTER TABLE identity_rate_limit_buckets DROP CHECK ck_identity_rate_limit_scope, ADD CONSTRAINT ck_identity_rate_limit_scope CHECK (scope IN ('ACCOUNT_REGISTRATION_EMAIL','ACCOUNT_REGISTRATION_PEER','EMAIL_VERIFICATION_RESEND_EMAIL','EMAIL_VERIFICATION_RESEND_PEER','EMAIL_VERIFICATION_ATTEMPT','EMAIL_VERIFICATION_PEER','PASSWORD_AUTHENTICATION_EMAIL','PASSWORD_AUTHENTICATION_PEER','PASSWORD_RECOVERY_REQUEST_EMAIL','PASSWORD_RECOVERY_REQUEST_PEER','PASSWORD_RECOVERY_ATTEMPT','PASSWORD_RECOVERY_ATTEMPT_PEER','MFA_AUTHENTICATION_ACCOUNT','MFA_AUTHENTICATION_PEER','PASSKEY_AUTHENTICATION_PEER','STEP_UP_ACCOUNT','STEP_UP_PEER','PASSKEY_REGISTRATION_ACCOUNT','PRIVILEGED_ACCESS_REQUEST_ACCOUNT','PRIVILEGED_ACCESS_REQUEST_PEER','PRIVILEGED_ACCESS_APPROVAL_ACCOUNT','PRIVILEGED_ACCESS_ACTIVATION_ACCOUNT','BREAK_GLASS_ACTIVATION_ACCOUNT','BREAK_GLASS_ACTIVATION_PEER','ACCOUNT_STATE_OPERATION_ACCOUNT','ACCOUNT_STATE_OPERATION_PEER','PERSON_PROFILE_MUTATION_ACCOUNT','PERSON_PROFILE_MUTATION_PEER','DEPENDENT_PROFILE_CREATION_ACCOUNT','DEPENDENT_PROFILE_CREATION_PEER','ORGANIZATION_MUTATION_ACCOUNT','ORGANIZATION_MUTATION_PEER','ORGANIZATION_UNIT_MUTATION_ACCOUNT','ORGANIZATION_UNIT_MUTATION_PEER','ORGANIZATION_AFFILIATION_REQUEST_ACCOUNT','ORGANIZATION_AFFILIATION_REQUEST_PEER','ORGANIZATION_AFFILIATION_RESPONSE_ACCOUNT','ORGANIZATION_AFFILIATION_RESPONSE_PEER','ORGANIZATION_AFFILIATION_MUTATION_ACCOUNT','ORGANIZATION_AFFILIATION_MUTATION_PEER','PROFILE_CLAIM_PAIRING_ACCOUNT','PROFILE_CLAIM_PAIRING_PEER','PROFILE_CLAIM_AUTHORIZATION_ACCOUNT','PROFILE_CLAIM_AUTHORIZATION_PEER','PROFILE_CLAIM_REDEMPTION_ACCOUNT','PROFILE_CLAIM_REDEMPTION_PEER','PROFILE_DUPLICATE_REPORT_ACCOUNT','PROFILE_DUPLICATE_REPORT_PEER','PROFILE_DUPLICATE_MUTATION_ACCOUNT','QURAN_GOVERNANCE_MUTATION_ACCOUNT','QURAN_GOVERNANCE_MUTATION_PEER','QURAN_PUBLIC_SEARCH_PEER','QURAN_PUBLIC_SEARCH_QUERY'))"),
            new SqlMigrationStep(new MigrationStepId('003_audit_subjects'), 'Preserve all exact audit subjects and add the corpus subject.', "ALTER TABLE security_audit_events DROP CHECK ck_security_audit_events_subject, ADD CONSTRAINT ck_security_audit_events_subject CHECK (subject_kind IN ('ACCOUNT','SESSION','DEVICE','ROLE_ASSIGNMENT','AUTHENTICATOR','RECOVERY_CODE_SET','PRIVILEGED_ACCESS','WORKSPACE','SECURITY_AUDIT','PERSON','PERSON_ROLE','GUARDIANSHIP','ORGANIZATION','ORGANIZATION_UNIT','ORGANIZATION_AFFILIATION','ORGANIZATION_AFFILIATION_ASSIGNMENT','PROFILE_CLAIM','PROFILE_VERIFICATION','PERSON_DUPLICATE_CASE','PERSON_ALIAS','QURAN_SOURCE','QURAN_RELEASE','QURAN_SEARCH_CORPUS'))"),
            new SqlMigrationStep(new MigrationStepId('004_operations'), 'Create replay-safe private corpus-validation operations.', <<<'SQL'
CREATE TABLE quran_search_corpus_validation_operations (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 public_id BINARY(16) NOT NULL,
 submission_id BINARY(16) NOT NULL,
 request_fingerprint BINARY(32) NOT NULL,
 corpus_id BIGINT UNSIGNED NOT NULL,
 actor_account_id BIGINT UNSIGNED NOT NULL,
 expected_version INT UNSIGNED NOT NULL,
 validation_public_id BINARY(16) NOT NULL,
 audit_event_public_id BINARY(16) NOT NULL,
 step_up_grant_id BIGINT UNSIGNED NOT NULL,
 occurred_at DATETIME(6) NOT NULL,
 created_at DATETIME(6) NOT NULL,
 PRIMARY KEY (id),
 UNIQUE KEY uq_quran_search_validation_operation_public (public_id),
 UNIQUE KEY uq_quran_search_validation_operation_submission (submission_id),
 UNIQUE KEY uq_quran_search_validation_operation_validation (validation_public_id),
 UNIQUE KEY uq_quran_search_validation_operation_audit (audit_event_public_id),
 KEY ix_quran_search_validation_operation_corpus (corpus_id, occurred_at, id),
 CONSTRAINT fk_quran_search_validation_operation_corpus FOREIGN KEY (corpus_id) REFERENCES quran_search_corpora(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_quran_search_validation_operation_actor FOREIGN KEY (actor_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_quran_search_validation_operation_step_up FOREIGN KEY (step_up_grant_id) REFERENCES account_step_up_grants(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_quran_search_validation_operation_version CHECK (expected_version >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
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
