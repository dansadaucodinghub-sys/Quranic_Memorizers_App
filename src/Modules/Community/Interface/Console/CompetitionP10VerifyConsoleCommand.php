<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Interface\Console;

use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;
use Qmdb\Modules\Community\Infrastructure\Persistence\MySqlCommunityFoundationVerificationRepository;

/** Read-only structural verifier; production readiness has separate gates. */
final readonly class CompetitionP10VerifyConsoleCommand implements ConsoleCommand
{
    public function __construct(private MySqlCommunityFoundationVerificationRepository $inventory)
    {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('competition:p10:verify');
    }

    public function description(): string
    {
        return 'Verify P10 Clip and community schema and authorization registration.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        try {
            $inventory = $this->inventory->verify();
            $output->write(
                "Competition P10 structural verification: PASS\n"
                . "Scope: schema and authorization registration only; not production closeout\n"
                . "Required tables: {$inventory['tables']}\n"
                . "Applied P10 migrations: {$inventory['migrations']}\n"
                . "P10 permissions: {$inventory['permissions']}\n"
            );
            return 0;
        } catch (\Throwable $error) {
            $output->write("Competition P10 structural verification: FAIL\n{$error->getMessage()}\n");
            return 1;
        }
    }
}
