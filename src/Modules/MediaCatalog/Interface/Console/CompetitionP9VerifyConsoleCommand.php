<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaCatalog\Interface\Console;

use PDO;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskMap;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;

/** Read-only P9 contract verifier; it never uploads, scans, processes, or repairs media. */
final readonly class CompetitionP9VerifyConsoleCommand implements ConsoleCommand
{
    private const TABLES = ['media_assets', 'media_upload_sessions', 'media_upload_parts', 'media_variants', 'media_processing_jobs', 'media_scan_results', 'media_events', 'media_holds', 'media_delivery_policies', 'media_operation_receipts', 'media_governance_operations', 'media_consent_reviews'];

    public function __construct(private DatabaseConnectionProvider $connections, private ScheduledTaskMap $tasks)
    {
    }
    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('competition:p9:verify');
    }
    public function description(): string
    {
        return 'Verify P9 private audio and video evidence runtime contracts.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        try {
            $pdo = $this->connections->connection();
            foreach (self::TABLES as $table) {
                $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table');
                if ($statement === false) {
                    throw new \RuntimeException('P9 verification statement could not be prepared.');
                }
                $statement->execute([':table' => $table]);
                if ((int) $statement->fetchColumn() !== 1) {
                    throw new \RuntimeException('Required P9 table is missing: ' . $table);
                }
            }
            $migration = $pdo->prepare("SELECT COUNT(*) FROM qmdb_schema_migrations WHERE migration_id IN ('20260915110000_create_media_foundation','20260915111000_extend_media_evidence_runtime','20260915113000_create_media_operation_receipts','20260917120000_complete_media_governance','20260917121000_extend_media_governance_security','20260917140000_create_media_consent_reviews') AND status='APPLIED'");
            if ($migration === false) {
                throw new \RuntimeException('P9 migration verification could not be prepared.');
            }
            $migration->execute();
            if ((int) $migration->fetchColumn() !== 6) {
                throw new \RuntimeException('P9 migrations are incomplete.');
            }
            $triggers = $pdo->prepare("SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND event_object_table='media_governance_operations' AND action_timing='BEFORE' AND ((trigger_name='trg_media_governance_no_update' AND event_manipulation='UPDATE') OR (trigger_name='trg_media_governance_no_delete' AND event_manipulation='DELETE'))");
            if ($triggers === false) {
                throw new \RuntimeException('P9 governance verification could not be prepared.');
            }
            $triggers->execute();
            if ((int) $triggers->fetchColumn() !== 2) {
                throw new \RuntimeException('P9 immutable governance receipt guards are incomplete.');
            }
            $seed = $pdo->prepare("SELECT COUNT(*) FROM qmdb_schema_seeds WHERE seed_id='20260915112000_seed_p9_media_authorization_catalog' AND status='APPLIED'");
            if ($seed === false) {
                throw new \RuntimeException('P9 seed verification could not be prepared.');
            }
            $seed->execute();
            if ((int) $seed->fetchColumn() !== 1) {
                throw new \RuntimeException('P9 authorization catalog is not applied.');
            }
            $permissions = $pdo->prepare("SELECT COUNT(*) FROM authorization_permissions WHERE owning_module IN ('media.catalog','media.ingestion','media.processing','media.moderation','media.delivery') AND status='ACTIVE'");
            if ($permissions === false) {
                throw new \RuntimeException('P9 permission verification could not be prepared.');
            }
            $permissions->execute();
            if ((int) $permissions->fetchColumn() !== 10) {
                throw new \RuntimeException('P9 permission catalog is incomplete.');
            }
            $registered = array_map(static fn ($task): string => $task->id()->value(), $this->tasks->tasks());
            foreach (['media.scans.process', 'media.processing.process', 'media.staging.cleanup', 'media.uploads.expire', 'media.assets.reconcile'] as $task) {
                if (!in_array($task, $registered, true)) {
                    throw new \RuntimeException('P9 scheduler task is missing: ' . $task);
                }
            }
            $output->write("Competition P9 verification: PASS\nScope: schema and registration contracts only; not production closeout\nRequired tables: 12\nApplied P9 migrations: 6\nGovernance receipt guards: 2\nP9 permissions: 10\nP9 scheduler tasks: 5\n");
            return 0;
        } catch (\Throwable $error) {
            $output->write("Competition P9 verification: FAIL\n{$error->getMessage()}\n");
            return 1;
        }
    }
}
