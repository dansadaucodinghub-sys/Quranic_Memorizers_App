<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

final readonly class CreateQuranReleaseOperationIdempotencyMigration implements Migration
{
    public function id(): MigrationId { return new MigrationId('20260910060300_create_quran_release_operation_idempotency'); }
    public function description(): string { return 'Create replay-safe, audit-linked Qur’an release transition operations.'; }
    public function dependencies(): array { return [(new ExtendQuranGovernanceSecurityCatalogMigration())->id()]; }
    public function up(): array { return [new SqlMigrationStep(new MigrationStepId('001_create_operations'), 'Create one immutable idempotency record per Qur’an release transition.', <<<'SQL'
CREATE TABLE quran_release_operations (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 public_id BINARY(16) NOT NULL,
 submission_id BINARY(16) NOT NULL,
 request_fingerprint BINARY(32) NOT NULL,
 operation_type VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 release_id BIGINT UNSIGNED NOT NULL,
 actor_account_id BIGINT UNSIGNED NOT NULL,
 previous_status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 new_status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 step_up_grant_id BIGINT UNSIGNED NULL,
 audit_event_public_id BINARY(16) NOT NULL,
 version_before INT UNSIGNED NOT NULL,
 version_after INT UNSIGNED NOT NULL,
 occurred_at DATETIME(6) NOT NULL,
 created_at DATETIME(6) NOT NULL,
 PRIMARY KEY (id),
 UNIQUE KEY uq_quran_release_operations_public_id (public_id),
 UNIQUE KEY uq_quran_release_operations_submission (submission_id),
 UNIQUE KEY uq_quran_release_operations_audit_event (audit_event_public_id),
 KEY ix_quran_release_operations_release_time (release_id, occurred_at, id),
 CONSTRAINT fk_quran_release_operations_release FOREIGN KEY (release_id) REFERENCES quran_reference_releases (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_quran_release_operations_actor FOREIGN KEY (actor_account_id) REFERENCES user_accounts (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_quran_release_operations_step_up FOREIGN KEY (step_up_grant_id) REFERENCES account_step_up_grants (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_quran_release_operations_type CHECK (operation_type IN ('STAGE','VALIDATE','APPROVE','ACTIVATE','REJECT')),
 CONSTRAINT ck_quran_release_operations_version CHECK (version_before >= 1 AND version_after = version_before + 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL)]; }
    public function down(): array { return []; }
    public function reversible(): bool { return false; }
}
