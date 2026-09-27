<?php

declare(strict_types=1);

namespace Qmdb\Modules\SearchAnalytics\Interface\Console;

use Qmdb\Modules\SearchAnalytics\Infrastructure\Persistence\MySqlP11Repository;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;

final readonly class CompetitionP11ProductionSmokeConsoleCommand implements ConsoleCommand
{
    public function __construct(private MySqlP11Repository $repository)
    {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('competition:p11:production-smoke:verify');
    }

    public function description(): string
    {
        return 'Run an isolated read-only P11 contract and query-plan smoke.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        try {
            $this->repository->verifyFoundation();
            $plan = $this->repository->verifySearchQueryPlan();
            $output->write("Competition P11 production-like smoke: PASS\nMode: isolated read-only contract smoke\n"
                . "Search query index: {$plan['key']}\nSearch access type: {$plan['type']}\n");
            return 0;
        } catch (\Throwable $error) {
            $output->write("Competition P11 production-like smoke: FAIL\n{$error->getMessage()}\n");
            return 1;
        }
    }
}
