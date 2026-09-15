<?php

declare(strict_types=1);

namespace Qmdb\Modules\CertificateIssuance\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/** Keeps certificate mutations replay-safe without storing browser request bodies. */
final readonly class CreateCertificateOperationIdempotencyMigration implements Migration
{
    public function id(): MigrationId { return new MigrationId('20260915106000_create_certificate_operation_idempotency'); }
    public function description(): string { return 'Create P8 certificate operation idempotency ledger.'; }
    public function dependencies(): array { return [(new CreateCertificateIssuanceMigration())->id()]; }
    public function up(): array
    {
        return [new SqlMigrationStep(new MigrationStepId('001_operations'), 'Create bounded certificate operation replay ledger.', "CREATE TABLE certificate_operations (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, public_id BINARY(16) NOT NULL, submission_id BINARY(16) NOT NULL, workspace_id BIGINT UNSIGNED NOT NULL, operation_code VARCHAR(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, request_fingerprint BINARY(32) NOT NULL, certificate_id BIGINT UNSIGNED NOT NULL, result_status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, version_after INT UNSIGNED NOT NULL, occurred_at DATETIME(6) NOT NULL, PRIMARY KEY(id), UNIQUE KEY uq_p8_certificate_operation_public(public_id), UNIQUE KEY uq_p8_certificate_operation_submission(submission_id), KEY ix_p8_certificate_operation_lookup(workspace_id,certificate_id,id), CONSTRAINT fk_p8_certificate_operation_certificate FOREIGN KEY(workspace_id,certificate_id) REFERENCES certificates(workspace_id,id) ON DELETE RESTRICT ON UPDATE RESTRICT, CONSTRAINT ck_p8_certificate_operation CHECK(version_after>=1 AND operation_code IN ('CERTIFICATE_PREPARE','CERTIFICATE_ISSUE','CERTIFICATE_REVOKE','CERTIFICATE_ARCHIVE')) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci")];
    }
    public function down(): array { return []; }
    public function reversible(): bool { return false; }
}
