<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\MySql;

use PDO;
use Qmdb\Tests\Support\MySql\MySqlIntegrationTestCase;

/** Oracle MySQL evidence for the P8 governed persistence boundary. */
final class CertificateP8SchemaIntegrationTest extends MySqlIntegrationTestCase
{
    public function testCertificateAndRecordGovernanceTablesAreInnoDbUtf8mb4(): void
    {
        $tables = [
            'certificate_templates', 'certificate_signing_keys', 'certificates',
            'certificate_number_sequences', 'certificate_artifacts', 'certificate_events', 'certificate_issuance_jobs', 'certificate_operations',
            'record_passports', 'record_passport_entries', 'record_passport_events',
            'record_passport_consents', 'record_passport_shares', 'record_passport_share_entries',
            'trusted_archive_streams', 'trusted_archive_records', 'trusted_archive_events',
            'trusted_archive_artifacts', 'trusted_archive_holds',
            'legacy_record_import_batches', 'legacy_record_import_rows', 'legacy_records',
            'legacy_record_import_events',
        ];
        $statement = $this->provider()->connection()->prepare('SELECT engine AS engine_name,table_collation AS collation_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table_name');
        self::assertNotFalse($statement);
        foreach ($tables as $table) {
            $statement->execute([':table_name' => $table]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            self::assertIsArray($row, $table . ' is missing.');
            $engine = $row['engine_name'] ?? null;
            $collation = $row['collation_name'] ?? null;
            self::assertIsString($engine);
            self::assertIsString($collation);
            self::assertSame('InnoDB', $engine);
            self::assertStringStartsWith('utf8mb4_', $collation);
        }
    }

    public function testP8MigrationsAndImmutabilityTriggersAreInstalled(): void
    {
        $migrations = [
            '20260915100000_create_certificate_governance',
            '20260915101000_create_certificate_issuance',
            '20260915102000_create_record_passports',
            '20260915103000_create_trusted_archive',
            '20260915104000_create_legacy_record_import',
            '20260915106000_create_certificate_operation_idempotency',
        ];
        $migration = $this->provider()->connection()->prepare("SELECT status FROM qmdb_schema_migrations WHERE migration_id=:migration_id");
        self::assertNotFalse($migration);
        foreach ($migrations as $id) {
            $migration->execute([':migration_id' => $id]);
            self::assertSame('APPLIED', $migration->fetchColumn(), $id . ' is not applied.');
        }
        $trigger = $this->provider()->connection()->prepare('SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND trigger_name=:trigger_name');
        self::assertNotFalse($trigger);
        foreach (['trg_p8_certificate_events_no_update', 'trg_p8_certificate_artifacts_no_update', 'trg_p8_passport_entries_no_update', 'trg_p8_archive_records_no_update', 'trg_p8_legacy_records_no_update'] as $name) {
            $trigger->execute([':trigger_name' => $name]);
            self::assertSame(1, (int) $trigger->fetchColumn(), $name . ' is missing.');
        }
    }

    public function testPrivateSigningMaterialCannotBePersistedInTheCertificateKeyTable(): void
    {
        $statement = $this->provider()->connection()->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='certificate_signing_keys' AND lower(column_name) LIKE '%private%'");
        self::assertNotFalse($statement);
        $statement->execute();
        self::assertSame(0, (int) $statement->fetchColumn());
    }
}
