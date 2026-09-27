<?php

declare(strict_types=1);

namespace Qmdb\Modules\ProductionHardening\Interface\Console;

use Qmdb\Modules\ProductionHardening\Infrastructure\Persistence\MySqlProductionHardeningRepository;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;

final readonly class CompetitionP12VerifyConsoleCommand implements ConsoleCommand
{
    public function __construct(private MySqlProductionHardeningRepository $repository)
    {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('competition:p12:verify');
    }

    public function description(): string
    {
        return 'Verify P12 quality, accessibility, privacy, resilience, and production-hardening foundations.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        try {
            $inventory = $this->repository->verifyFoundation();
            $output->write("Competition P12 structural verification: PASS\n"
                . "Scope: Quality, Accessibility, and Production Hardening; pilot/offline rollout is P13\n"
                . "Required tables: {$inventory['tables']}\nApplied P12 migrations: {$inventory['migrations']}\n"
                . "P12 permissions: {$inventory['permissions']}\nProcessing purposes: {$inventory['purposes']}\n"
                . "Privacy notices: {$inventory['notices']}\nNotification templates: {$inventory['templates']}\n"
                . "Retention policies: {$inventory['policies']}\nOperational services: {$inventory['services']}\nSLIs: {$inventory['slis']}\n");
            return 0;
        } catch (\Throwable $error) {
            $output->write("Competition P12 structural verification: FAIL\n{$error->getMessage()}\n");
            return 1;
        }
    }
}
