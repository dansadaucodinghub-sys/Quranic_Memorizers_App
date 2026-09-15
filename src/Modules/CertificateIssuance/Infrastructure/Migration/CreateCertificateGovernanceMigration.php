<?php

declare(strict_types=1);

namespace Qmdb\Modules\CertificateIssuance\Infrastructure\Migration;

use Qmdb\Modules\CompetitionPublication\Infrastructure\Migration\CreateCompetitionResultPublicationProjectionMigration;
use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/** Versioned certificate templates, public key metadata, and row-locked numbering. */
final readonly class CreateCertificateGovernanceMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260915100000_create_certificate_governance');
    }

    public function description(): string
    {
        return 'Create P8 certificate templates, public signing keys, and number sequences.';
    }

    public function dependencies(): array
    {
        return [(new CreateCompetitionResultPublicationProjectionMigration())->id()];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_templates'), 'Create versioned non-executable certificate templates.', <<<'SQL'
CREATE TABLE certificate_templates (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL,
 template_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, template_version SMALLINT UNSIGNED NOT NULL,
 name_en VARCHAR(160) NOT NULL, name_ar VARCHAR(160) NOT NULL, locale_mode VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 layout_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, page_size_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 orientation_code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, configuration_schema_version SMALLINT UNSIGNED NOT NULL,
 configuration_canonical_json JSON NOT NULL, configuration_sha256 BINARY(32) NOT NULL, status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'DRAFT',
 active_marker TINYINT GENERATED ALWAYS AS (CASE WHEN status='ACTIVE' THEN 1 ELSE NULL END) STORED,
 version INT UNSIGNED NOT NULL DEFAULT 1, created_by_account_id BIGINT UNSIGNED NOT NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 activated_at DATETIME(6) NULL, retired_at DATETIME(6) NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p8_template_public(public_id), UNIQUE KEY uq_p8_template_version(workspace_id,template_code,template_version),
 UNIQUE KEY uq_p8_template_active(workspace_id,template_code,active_marker), UNIQUE KEY uq_p8_template_workspace_id(workspace_id,id),
 KEY ix_p8_template_workspace_status(workspace_id,status,template_code,id),
 CONSTRAINT fk_p8_template_workspace FOREIGN KEY(workspace_id) REFERENCES workspaces(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_p8_template_creator FOREIGN KEY(created_by_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p8_template CHECK(template_version>=1 AND configuration_schema_version>=1 AND version>=1 AND locale_mode IN ('ENGLISH','ARABIC','BILINGUAL') AND page_size_code IN ('A4','LETTER') AND orientation_code IN ('PORTRAIT','LANDSCAPE') AND status IN ('DRAFT','ACTIVE','RETIRED') AND (status<>'ACTIVE' OR activated_at IS NOT NULL) AND (status<>'RETIRED' OR retired_at IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('002_signing_keys'), 'Create platform-governed Ed25519 public-key metadata.', <<<'SQL'
CREATE TABLE certificate_signing_keys (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, key_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 algorithm VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, public_key BINARY(32) NOT NULL, public_key_sha256 BINARY(32) NOT NULL,
 provider_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, provider_key_reference VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, active_marker TINYINT GENERATED ALWAYS AS (CASE WHEN status='ACTIVE' THEN 1 ELSE NULL END) STORED,
 valid_from DATETIME(6) NOT NULL, valid_until DATETIME(6) NULL, version INT UNSIGNED NOT NULL DEFAULT 1,
 created_by_account_id BIGINT UNSIGNED NOT NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, activated_at DATETIME(6) NULL, retired_at DATETIME(6) NULL, revoked_at DATETIME(6) NULL, revocation_reason_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p8_key_public(public_id), UNIQUE KEY uq_p8_key_code(key_code), UNIQUE KEY uq_p8_key_active(active_marker),
 KEY ix_p8_key_status_validity(status,valid_from,valid_until,id),
 CONSTRAINT fk_p8_key_creator FOREIGN KEY(created_by_account_id) REFERENCES user_accounts(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p8_key CHECK(algorithm='ED25519' AND version>=1 AND status IN ('ACTIVE','VERIFY_ONLY','RETIRED','REVOKED') AND (status<>'ACTIVE' OR activated_at IS NOT NULL) AND (status<>'RETIRED' OR retired_at IS NOT NULL) AND (status<>'REVOKED' OR (revoked_at IS NOT NULL AND revocation_reason_code IS NOT NULL)) AND (valid_until IS NULL OR valid_until>valid_from))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('003_number_sequences'), 'Create concurrency-safe workspace certificate number sequences.', <<<'SQL'
CREATE TABLE certificate_number_sequences (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL,
 sequence_scope VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, sequence_year SMALLINT UNSIGNED NOT NULL, next_sequence BIGINT UNSIGNED NOT NULL,
 version INT UNSIGNED NOT NULL DEFAULT 1, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p8_sequence_public(public_id), UNIQUE KEY uq_p8_sequence_scope(workspace_id,sequence_scope,sequence_year), UNIQUE KEY uq_p8_sequence_workspace_id(workspace_id,id),
 CONSTRAINT fk_p8_sequence_workspace FOREIGN KEY(workspace_id) REFERENCES workspaces(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p8_sequence CHECK(sequence_year BETWEEN 2000 AND 9999 AND next_sequence>=1 AND version>=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
        ];
    }

    public function down(): array { return []; }
    public function reversible(): bool { return false; }
}
