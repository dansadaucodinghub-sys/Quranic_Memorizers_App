<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionRegistration\Interface\Console;

use PDO;
use PDOStatement;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;

/** Read-only verification of P5 registration and roster storage contracts. */
final readonly class CompetitionRegistrationVerifyConsoleCommand implements ConsoleCommand
{
    public function __construct(private DatabaseConnectionProvider $connections)
    {
    }
    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('competition:registration:verify');
    }
    public function description(): string
    {
        return 'Verify P5 competition registration, append-only evidence, and roster storage.';
    }
    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        try {
            $pdo = $this->connections->connection();
            $tables = ['competition_registrations','competition_registration_eligibility_evidence','competition_registration_consents','competition_registration_reviews','competition_registration_events','competition_rosters','competition_roster_entries'];
            foreach ($tables as $table) {
                $this->requireTable($pdo, $table);
            }
            $triggers = $this->count($pdo, "SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND trigger_name LIKE 'trg_competition_%'");
            if ($triggers < 11) {
                throw new \RuntimeException('Competition append-only trigger set is incomplete.');
            }
            $output->write("Competition registration verification: PASS\nTables: " . count($tables) . "\nAppend-only triggers: {$triggers}\n");
            return 0;
        } catch (\Throwable $error) {
            $output->write("Competition registration verification: FAIL\n{$error->getMessage()}\n");
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
