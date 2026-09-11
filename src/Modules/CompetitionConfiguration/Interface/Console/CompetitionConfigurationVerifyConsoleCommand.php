<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionConfiguration\Interface\Console;

use PDO;
use PDOStatement;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;

/** Read-only verification of P5 configuration foundations. */
final readonly class CompetitionConfigurationVerifyConsoleCommand implements ConsoleCommand
{
    public function __construct(private DatabaseConnectionProvider $connections)
    {
    }
    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('competition:configuration:verify');
    }
    public function description(): string
    {
        return 'Verify P5 competition configuration tables, tenant keys, and authorization catalog.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        try {
            $pdo = $this->connections->connection();
            $tables = ['competition_programs','competition_program_organizers','competition_editions','competition_edition_organizers','competition_venues','competition_categories','competition_category_quran_segments','competition_eligibility_rules','competition_registration_windows','competition_category_capacity_states','competition_edition_configuration_snapshots','competition_edition_events'];
            foreach ($tables as $table) {
                $this->requireTable($pdo, $table);
            }
            foreach (['competition_programs','competition_editions','competition_categories','competition_registration_windows','competition_category_capacity_states'] as $table) {
                $this->requireWorkspaceKey($pdo, $table);
            }
            $permissions = $this->count($pdo, "SELECT COUNT(*) FROM authorization_permissions WHERE owning_module='competition.configuration' AND status='ACTIVE'");
            if ($permissions !== 12) {
                throw new \RuntimeException('Competition permission catalog is incomplete.');
            }
            $output->write("Competition configuration verification: PASS\nTables: " . count($tables) . "\nPermissions: {$permissions}\n");
            return 0;
        } catch (\Throwable $error) {
            $output->write("Competition configuration verification: FAIL\n{$error->getMessage()}\n");
            return 1;
        }
    }

    private function requireTable(PDO $pdo, string $table): void
    {
        $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table');
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Table verification preparation failed.');
        }
        $statement->execute([':table' => $table]);
        if ($this->value($statement) !== 1) {
            throw new \RuntimeException("Missing required P5 table: {$table}.");
        }
    }

    private function requireWorkspaceKey(PDO $pdo, string $table): void
    {
        $statement = $pdo->prepare("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=:table AND column_name='workspace_id'");
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Index verification preparation failed.');
        }
        $statement->execute([':table' => $table]);
        if ($this->value($statement) < 1) {
            throw new \RuntimeException("Missing workspace index: {$table}.");
        }
    }

    private function count(PDO $pdo, string $sql): int
    {
        $statement = $pdo->query($sql);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Verification query failed.');
        }
        return $this->value($statement);
    }

    private function value(PDOStatement $statement): int
    {
        $value = $statement->fetchColumn();
        if (!is_int($value) && !is_string($value)) {
            throw new \RuntimeException('Verification count is invalid.');
        }
        return (int) $value;
    }
}
