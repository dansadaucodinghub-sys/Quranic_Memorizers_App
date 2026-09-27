<?php

declare(strict_types=1);

namespace Qmdb\Modules\SearchAnalytics\Infrastructure\Migration;

use Qmdb\Modules\Community\Infrastructure\Migration\CreateCommunityNotificationIntentMigration;
use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/** QMDB-MIG-016: rebuildable, privacy-aware search projections. */
final readonly class CreateSearchProjectionFoundationMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260925100000_create_search_projection_foundation');
    }

    public function description(): string
    {
        return 'Create rebuildable search documents, source events, and projection checkpoints.';
    }

    public function dependencies(): array
    {
        return [(new CreateCommunityNotificationIntentMigration())->id()];
    }

    public function reversible(): bool
    {
        return false;
    }

    public function down(): array
    {
        return [];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_documents'), 'Create minimized search projection documents.', <<<'SQL'
CREATE TABLE search_projection_documents (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NULL,
 source_kind VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, source_public_id BINARY(16) NOT NULL,
 source_version INT UNSIGNED NOT NULL, visibility_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 status_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, provenance_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 locale VARCHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, title VARCHAR(240) NOT NULL, summary VARCHAR(600) NOT NULL,
 normalized_latin VARCHAR(900) NOT NULL, normalized_arabic VARCHAR(900) NOT NULL, safe_payload_json JSON NOT NULL,
 schema_version SMALLINT UNSIGNED NOT NULL, source_updated_at DATETIME(6) NOT NULL, projected_at DATETIME(6) NOT NULL,
 checksum BINARY(32) NOT NULL, retired_at DATETIME(6) NULL,
 current_marker TINYINT GENERATED ALWAYS AS (CASE WHEN retired_at IS NULL THEN 1 ELSE NULL END) STORED,
 PRIMARY KEY(id), UNIQUE KEY uq_p11_search_public(public_id),
 UNIQUE KEY uq_p11_search_current(source_kind,source_public_id,current_marker),
 KEY ix_p11_search_public(visibility_code,status_code,locale,projected_at,id),
 KEY ix_p11_search_workspace(workspace_id,visibility_code,status_code,projected_at,id),
 FULLTEXT KEY ft_p11_search_text(title,summary,normalized_latin,normalized_arabic),
 CONSTRAINT fk_p11_search_workspace FOREIGN KEY(workspace_id) REFERENCES workspaces(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p11_search_document CHECK(source_kind IN ('COMPETITION_RESULT','CERTIFICATE','RECITATION_CLIP','ORGANIZATION','PERSON_PUBLIC_PROFILE') AND visibility_code IN ('PUBLIC','WORKSPACE','PLATFORM') AND provenance_code IN ('AUTHORITATIVE','DERIVED') AND locale IN ('en','ar') AND source_version>=1 AND schema_version>=1 AND JSON_VALID(safe_payload_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('002_events'), 'Create append-only search projection source events.', <<<'SQL'
CREATE TABLE search_projection_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NULL,
 source_kind VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, source_public_id BINARY(16) NOT NULL,
 source_version INT UNSIGNED NOT NULL, event_code VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 payload_json JSON NOT NULL, occurred_at DATETIME(6) NOT NULL, accepted_at DATETIME(6) NOT NULL,
 PRIMARY KEY(id), UNIQUE KEY uq_p11_search_event_public(public_id),
 UNIQUE KEY uq_p11_search_event_source(source_kind,source_public_id,source_version),
 KEY ix_p11_search_event_unapplied(id,source_kind),
 CONSTRAINT fk_p11_search_event_workspace FOREIGN KEY(workspace_id) REFERENCES workspaces(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_p11_search_event CHECK(event_code IN ('UPSERTED','VISIBILITY_CHANGED','RETIRED','CONSENT_REVOKED') AND source_version>=1 AND JSON_VALID(payload_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('003_checkpoints'), 'Create deterministic projection checkpoints.', <<<'SQL'
CREATE TABLE search_projection_checkpoints (
 projection_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, last_event_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
 schema_version SMALLINT UNSIGNED NOT NULL, rebuilt_at DATETIME(6) NULL, updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(projection_code), CONSTRAINT ck_p11_search_checkpoint CHECK(schema_version>=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL),
            new SqlMigrationStep(new MigrationStepId('004_event_update'), 'Reject search event changes.', <<<'SQL'
CREATE TRIGGER trg_p11_search_event_no_update BEFORE UPDATE ON search_projection_events
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Search projection events are immutable'
SQL),
            new SqlMigrationStep(new MigrationStepId('005_event_delete'), 'Reject search event deletion.', <<<'SQL'
CREATE TRIGGER trg_p11_search_event_no_delete BEFORE DELETE ON search_projection_events
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Search projection events are immutable'
SQL),
        ];
    }
}
