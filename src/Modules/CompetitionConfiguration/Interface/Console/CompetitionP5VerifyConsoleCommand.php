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

/** A read-only P5 foundation verifier that remains valid after the authorized P6 extension. */
final readonly class CompetitionP5VerifyConsoleCommand implements ConsoleCommand
{
    public function __construct(private DatabaseConnectionProvider $connections)
    {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('competition:p5:verify');
    }

    public function description(): string
    {
        return 'Verify P5 database foundations and enforce the deferred P7 boundary.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        try {
            $pdo = $this->connections->connection();
            $activeRelease = $this->count($pdo, "SELECT COUNT(*) FROM quran_reference_releases WHERE status='ACTIVE'");
            if ($activeRelease !== 1) {
                throw new \RuntimeException('Exactly one active Qur’an release is required for P5 configuration.');
            }
            $forbidden = $this->count($pdo, "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name REGEXP '(^certificate|^media|^audio|^video|^social|payment|livestream|live_score)'");
            if ($forbidden !== 0) {
                throw new \RuntimeException('A deferred P7 persistence artifact exists.');
            }
            $output->write("Competition P5 verification: PASS\nActive Qur’an releases: 1\nDeferred P7 persistence artifacts: 0\n");
            return 0;
        } catch (\Throwable $error) {
            $output->write("Competition P5 verification: FAIL\n{$error->getMessage()}\n");
            return 1;
        }
    }

    private function count(PDO $pdo, string $sql): int
    {
        $statement = $pdo->query($sql);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('P5 verification query failed.');
        }
        $value = $statement->fetchColumn();
        if (!is_int($value) && !is_string($value)) {
            throw new \RuntimeException('P5 verification count is invalid.');
        }
        return (int) $value;
    }
}
