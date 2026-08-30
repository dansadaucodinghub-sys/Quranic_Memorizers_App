<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Infrastructure\Migration;

use Qmdb\Shared\Schema\Migration\Migration;
use Qmdb\Shared\Schema\Migration\MigrationId;
use Qmdb\Shared\Schema\Migration\MigrationStepId;
use Qmdb\Shared\Schema\Migration\SqlMigrationStep;

/**
 * Native MySQL JSON normalizes serialized values on retrieval.  Audit HMACs cover the
 * application canonical bytes, so evidence must retain those exact validated bytes.
 */
final readonly class PreserveCanonicalAuditMetadataMigration implements Migration
{
    public function id(): MigrationId
    {
        return new MigrationId('20260826012700_preserve_canonical_audit_metadata');
    }

    public function description(): string
    {
        return 'Preserve exact canonical JSON bytes used by security-audit integrity hashes.';
    }

    public function dependencies(): array
    {
        return [new MigrationId('20260826012400_create_security_audit_streams')];
    }

    public function up(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_preserve_canonical_json_bytes'), 'Store canonical audit JSON without MySQL reserialization.', <<<'SQL'
ALTER TABLE security_audit_events
    MODIFY metadata_canonical_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
    ADD CONSTRAINT ck_security_audit_events_metadata_valid CHECK (JSON_VALID(metadata_canonical_json))
SQL),
        ];
    }

    public function down(): array
    {
        return [
            new SqlMigrationStep(new MigrationStepId('001_restore_native_json'), 'Restore native MySQL JSON storage.', <<<'SQL'
ALTER TABLE security_audit_events
    DROP CHECK ck_security_audit_events_metadata_valid,
    MODIFY metadata_canonical_json JSON NOT NULL
SQL),
        ];
    }

    public function reversible(): bool
    {
        return true;
    }
}
