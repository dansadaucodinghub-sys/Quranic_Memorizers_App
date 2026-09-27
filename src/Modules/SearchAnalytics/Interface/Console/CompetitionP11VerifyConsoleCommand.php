<?php

declare(strict_types=1);

namespace Qmdb\Modules\SearchAnalytics\Interface\Console;

use Qmdb\Modules\SearchAnalytics\Infrastructure\Persistence\MySqlP11Repository;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;

final readonly class CompetitionP11VerifyConsoleCommand implements ConsoleCommand
{
    public function __construct(private MySqlP11Repository $repository)
    {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('competition:p11:verify');
    }

    public function description(): string
    {
        return 'Verify P11 search, analytics, reporting, export, and authority foundations.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        try {
            $inventory = $this->repository->verifyFoundation();
            $output->write("Competition P11 structural verification: PASS\n"
                . "Scope: Search, Analytics, Reporting, and Export; offline operations are P13\n"
                . "Required tables: {$inventory['tables']}\n"
                . "Applied P11 migrations: {$inventory['migrations']}\n"
                . "P11 permissions: {$inventory['permissions']}\n"
                . "Active metrics: {$inventory['metrics']}\n"
                . "Active reports: {$inventory['reports']}\n");
            return 0;
        } catch (\Throwable $error) {
            $output->write("Competition P11 structural verification: FAIL\n{$error->getMessage()}\n");
            return 1;
        }
    }
}
