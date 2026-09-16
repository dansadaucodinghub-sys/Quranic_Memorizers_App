<?php

declare(strict_types=1);

namespace Qmdb\Modules\CertificateIssuance\Interface\Console;

use PDO;
use PDOStatement;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;

/** Read-only P8 integrity verification; it never signs, issues, or repairs data. */
final readonly class CompetitionP8VerifyConsoleCommand implements ConsoleCommand
{
    /** @var list<string> */
    private const TABLES = [
        'certificate_templates', 'certificate_signing_keys', 'certificate_number_sequences', 'certificates',
        'certificate_artifacts', 'certificate_events', 'certificate_issuance_jobs', 'record_passports',
        'record_passport_entries', 'record_passport_events', 'record_passport_consents', 'record_passport_shares',
        'record_passport_share_entries', 'trusted_archive_streams', 'trusted_archive_records', 'trusted_archive_artifacts',
        'trusted_archive_events', 'trusted_archive_holds', 'legacy_record_import_batches', 'legacy_record_import_rows',
        'legacy_records', 'legacy_record_import_events', 'certificate_operations',
    ];

    /** @var list<string> */
    private const MIGRATIONS = [
        '20260915100000_create_certificate_governance', '20260915101000_create_certificate_issuance',
        '20260915102000_create_record_passports', '20260915103000_create_trusted_archive',
        '20260915104000_create_legacy_record_import', '20260915106000_create_certificate_operation_idempotency',
    ];

    public function __construct(private DatabaseConnectionProvider $connections)
    {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('competition:p8:verify');
    }

    public function description(): string
    {
        return 'Verify P8 certificate, passport, archive, and legacy-record integrity contracts.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        try {
            $pdo = $this->connections->connection();
            foreach (self::TABLES as $table) {
                $this->requireCount($pdo, 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:value', 1, 'Required P8 table is missing: ' . $table, [':value' => $table]);
            }
            foreach (self::MIGRATIONS as $migration) {
                $this->requireCount($pdo, "SELECT COUNT(*) FROM qmdb_schema_migrations WHERE migration_id=:value AND status='APPLIED'", 1, 'Required P8 migration is not applied: ' . $migration, [':value' => $migration]);
            }
            $this->requireCount($pdo, "SELECT COUNT(*) FROM qmdb_schema_seeds WHERE seed_id='20260915105000_seed_p8_authorization_catalog' AND status='APPLIED'", 1, 'P8 authorization seed is not applied.');
            $this->requireCount($pdo, "SELECT COUNT(*) FROM authorization_permissions WHERE owning_module='certificate.issuance' AND status='ACTIVE'", 18, 'P8 permission catalog is incomplete.');
            $this->requireCount($pdo, "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='certificate_signing_keys' AND column_name REGEXP 'private|secret'", 0, 'Signing-key metadata must not persist private material.');
            $this->requireCount($pdo, "SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND trigger_name IN ('trg_p8_certificate_events_no_update','trg_p8_certificate_artifacts_no_update','trg_p8_passport_entries_no_update','trg_p8_archive_records_no_update','trg_p8_legacy_records_no_update')", 5, 'P8 immutability triggers are incomplete.');
            $this->requireCount($pdo, "SELECT COUNT(*) FROM certificate_signing_keys WHERE algorithm <> 'ED25519'", 0, 'Unsupported certificate signing-key algorithm exists.');
            $this->requireCount($pdo, "SELECT COUNT(*) FROM certificates c INNER JOIN competition_result_publications p ON p.workspace_id=c.workspace_id AND p.id=c.result_publication_id WHERE c.status IN ('PREPARED','ISSUED') AND p.status<>'FINALIZED'", 0, 'A current certificate does not originate from a FINALIZED publication.');
            $this->requireCount($pdo, "SELECT COUNT(*) FROM certificates WHERE status='ISSUED' AND (manifest_sha256 IS NULL OR detached_signature IS NULL OR pdf_sha256 IS NULL OR signature_algorithm<>'ED25519')", 0, 'An issued certificate lacks immutable signing evidence.');
            $this->requireCount($pdo, "SELECT COUNT(*) FROM certificates WHERE certificate_number NOT REGEXP '^QMDB-[2-9][0-9]{3}-[A-Z0-9][A-Z0-9_-]{0,63}-[0-9]{6}$'", 0, 'A certificate does not satisfy the governed serial contract.');
            $this->requireCount($pdo, 'SELECT COUNT(*) FROM certificates WHERE OCTET_LENGTH(verification_code_hash)<>32 OR OCTET_LENGTH(verification_code_fingerprint)<>16', 0, 'A certificate does not satisfy the governed verification-code storage contract.');
            $output->write('Competition P8 verification: PASS' . "\nRequired tables: " . count(self::TABLES) . "\nApplied P8 migrations: " . count(self::MIGRATIONS) . "\nP8 permissions: 18\nPrivate key columns: 0\nSerial contract: QMDB-YYYY-WORKSPACE-######\nVerification-code contract: 256-bit Base64URL, SHA-256 only\n");

            return 0;
        } catch (\Throwable $error) {
            $output->write("Competition P8 verification: FAIL\n{$error->getMessage()}\n");

            return 1;
        }
    }

    /** @param array<string, scalar|null> $parameters */
    private function requireCount(PDO $pdo, string $sql, int $expected, string $message, array $parameters = []): void
    {
        $statement = $pdo->prepare($sql);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('P8 verification query could not be prepared.');
        }
        $statement->execute($parameters);
        if ((int) $statement->fetchColumn() !== $expected) {
            throw new \RuntimeException($message);
        }
    }
}
