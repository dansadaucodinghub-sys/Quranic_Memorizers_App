<?php

declare(strict_types=1);

namespace Qmdb\Modules\PilotOfflineRollout\Interface\Console;

use Qmdb\Modules\PilotOfflineRollout\Application\P13MaintenanceService;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;

final readonly class P13RuntimeConsoleCommand implements ConsoleCommand
{
    public function __construct(private string $command, private string $operation, private P13MaintenanceService $maintenance)
    {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName($this->command);
    }

    public function description(): string
    {
        return 'Run bounded P13 ' . str_replace(':', ' ', $this->operation) . ' verification or maintenance.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions(['dry-run']);
        $dryRun = $input->requireFlag('dry-run');
        try {
            $result = $this->maintenance->run($this->operation, $dryRun);
            $output->write("P13 runtime operation: PASS\nOperation: {$this->operation}\nExamined: {$result['examined']}\nChanged: {$result['changed']}\nDry run: " . ($dryRun ? 'yes' : 'no') . "\n");

            return 0;
        } catch (\Throwable $error) {
            $output->write("P13 runtime operation: FAIL\nOperation: {$this->operation}\n{$error->getMessage()}\n");

            return 1;
        }
    }
}
