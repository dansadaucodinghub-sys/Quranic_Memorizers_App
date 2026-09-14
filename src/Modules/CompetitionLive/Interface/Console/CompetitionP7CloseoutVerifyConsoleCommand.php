<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionLive\Interface\Console;

use Qmdb\Modules\CompetitionLive\Infrastructure\Readiness\CompetitionP7CloseoutReadinessCheck;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;

/** Read-only executable P7-CLOSE gate; formal project-state changes are separate. */
final readonly class CompetitionP7CloseoutVerifyConsoleCommand implements ConsoleCommand
{
    public function __construct(private CompetitionP7CloseoutReadinessCheck $readiness)
    {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('competition:p7:closeout:verify');
    }

    public function description(): string
    {
        return 'Verify P7 live, publication, appeal, outbox, and route-security closeout controls without changing state.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        try {
            $report = $this->readiness->verify();
            $output->write(sprintf(
                "Competition P7 closeout readiness: PASS\nP7 InnoDB tables: %d\nApplied P7 migrations: %d\nImmutable-history triggers: %d\nPrivate lifecycle routes: %d\nPublic live-projection routes: %d\nP7 scheduler tasks: %d\nFormal closeout: NOT PERFORMED\n",
                $report['tables'],
                $report['migrations'],
                $report['triggers'],
                $report['private_routes'],
                $report['public_routes'],
                $report['scheduler_tasks'],
            ));

            return 0;
        } catch (\Throwable $error) {
            $output->write("Competition P7 closeout readiness: FAIL\n{$error->getMessage()}\n");

            return 1;
        }
    }
}
