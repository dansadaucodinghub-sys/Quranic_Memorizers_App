<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionLive\Interface\Console;

use PDO;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;

/** Read-only verifier for the implemented P7 live, publication, and appeal foundations. */
final readonly class CompetitionP7VerifyConsoleCommand implements ConsoleCommand
{
    public function __construct(private DatabaseConnectionProvider $connections)
    {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('competition:p7:verify');
    }

    public function description(): string
    {
        return 'Verify P7 live operations, public projections, publication, and appeal adjudication foundations.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        try {
            $pdo = $this->connections->connection();
            $tables = ['competition_live_sessions','competition_live_participant_states','competition_live_events','competition_live_projection_streams','competition_live_projection_snapshots','competition_live_delivery_offsets','competition_live_operations','competition_result_publications','competition_result_publication_events','competition_result_publication_holds','competition_result_packages','competition_appeal_review_assignments','competition_appeal_reviewer_conflicts','competition_appeal_decisions','competition_appeal_correction_authorizations','competition_p7_outbox_messages','competition_p7_operations'];
            $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table');
            if ($statement === false) {
                throw new \RuntimeException('P7 table verification statement could not be prepared.');
            }
            foreach ($tables as $table) {
                $statement->execute([':table' => $table]);
                if ((int) $statement->fetchColumn() !== 1) {
                    throw new \RuntimeException("Required P7 table is missing: {$table}");
                }
            }
            $this->requireCount($pdo, "SELECT COUNT(*) FROM qmdb_schema_migrations WHERE status='APPLIED' AND migration_id IN ('20260912140000_create_competition_live_operations','20260912141000_create_competition_live_projections','20260912142000_add_competition_live_operation_sequence','20260912143000_create_competition_result_publication','20260912144000_create_competition_appeal_adjudication','20260913100000_create_competition_p7_outbox','20260913110000_create_competition_p7_operation_receipts')", 7, 'P7 migrations are incomplete.');
            $this->requireCount($pdo, "SELECT COUNT(*) FROM qmdb_schema_seeds WHERE status='APPLIED' AND seed_id='20260912145000_seed_competition_p7_authorization_catalog'", 1, 'P7 authorization seed is not applied.');
            $this->requireCount($pdo, "SELECT COUNT(*) FROM authorization_permissions WHERE owning_module='competition.live_operations' AND status='ACTIVE'", 7, 'P7 permission catalog is incomplete.');
            foreach (['trg_p7_live_events_no_update','trg_p7_live_events_no_delete','trg_p7_projection_snapshots_no_update','trg_p7_projection_snapshots_no_delete','trg_p7_publication_events_no_update','trg_p7_publication_events_no_delete','trg_p7_result_packages_no_update','trg_p7_result_packages_no_delete','trg_p7_appeal_decisions_no_update','trg_p7_appeal_decisions_no_delete'] as $trigger) {
                $this->requireCount($pdo, 'SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND trigger_name=:trigger', 1, "P7 immutability trigger is missing: {$trigger}", [':trigger' => $trigger]);
            }
            $output->write("Competition P7 verification: PASS\nRequired tables: " . count($tables) . "\nApplied P7 migrations: 7\nP7 permissions: 7\n");

            return 0;
        } catch (\Throwable $error) {
            $output->write("Competition P7 verification: FAIL\n{$error->getMessage()}\n");

            return 1;
        }
    }

    /** @param array<string, scalar> $parameters */
    private function requireCount(PDO $pdo, string $sql, int $expected, string $message, array $parameters = []): void
    {
        $statement = $pdo->prepare($sql);
        if ($statement === false) {
            throw new \RuntimeException('P7 verification statement could not be prepared.');
        }
        $statement->execute($parameters);
        if ((int) $statement->fetchColumn() !== $expected) {
            throw new \RuntimeException($message);
        }
    }
}
