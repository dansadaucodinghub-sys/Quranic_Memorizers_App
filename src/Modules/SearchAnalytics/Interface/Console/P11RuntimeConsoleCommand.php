<?php

declare(strict_types=1);

namespace Qmdb\Modules\SearchAnalytics\Interface\Console;

use Qmdb\Modules\SearchAnalytics\Infrastructure\Persistence\MySqlP11Repository;
use Qmdb\Modules\SearchAnalytics\Infrastructure\Persistence\P11MaintenanceService;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;

final readonly class P11RuntimeConsoleCommand implements ConsoleCommand
{
    public function __construct(
        private string $command,
        private string $operation,
        private MySqlP11Repository $repository,
        private P11MaintenanceService $maintenance,
    ) {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName($this->command);
    }

    public function description(): string
    {
        return 'Run bounded P11 ' . str_replace(':', ' ', $this->operation) . ' maintenance.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions(['dry-run']);
        $dryRun = $input->requireFlag('dry-run');
        try {
            $result = match ($this->operation) {
                'metrics:verify', 'reports:verify', 'search:verify' => $this->verify(),
                'snapshots:process' => $this->maintenance->processSnapshots($dryRun),
                'snapshots:rebuild' => $this->maintenance->rebuildSnapshots($dryRun),
                'snapshots:reconcile' => $this->maintenance->reconcileSnapshots(),
                'reports:process' => $this->maintenance->processReports($dryRun),
                'reports:reconcile' => $this->maintenance->reconcileReports(),
                'exports:cleanup' => $this->maintenance->cleanupExports($dryRun),
                'search:rebuild' => $this->maintenance->rebuildSearch($dryRun),
                'search:reconcile' => $this->maintenance->reconcileSearch(),
                default => throw new \RuntimeException('Unknown P11 runtime operation.'),
            };
            $output->write("P11 runtime operation: PASS\nOperation: {$this->operation}\n"
                . "Examined: {$result['examined']}\nChanged: {$result['changed']}\n"
                . 'Dry run: ' . ($dryRun ? 'yes' : 'no') . "\n");
            return 0;
        } catch (\Throwable $error) {
            $output->write("P11 runtime operation: FAIL\nOperation: {$this->operation}\n{$error->getMessage()}\n");
            return 1;
        }
    }

    /** @return array{examined:int,changed:int} */
    private function verify(): array
    {
        $this->repository->verifyFoundation();
        return ['examined' => 1, 'changed' => 0];
    }
}
