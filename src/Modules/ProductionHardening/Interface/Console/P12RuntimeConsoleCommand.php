<?php

declare(strict_types=1);

namespace Qmdb\Modules\ProductionHardening\Interface\Console;

use Qmdb\Modules\ProductionHardening\Infrastructure\Persistence\MySqlProductionHardeningRepository;
use Qmdb\Modules\ProductionHardening\Infrastructure\Persistence\P12MaintenanceService;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;

final readonly class P12RuntimeConsoleCommand implements ConsoleCommand
{
    public function __construct(
        private string $command,
        private string $operation,
        private MySqlProductionHardeningRepository $repository,
        private P12MaintenanceService $maintenance,
    ) {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName($this->command);
    }

    public function description(): string
    {
        return 'Run bounded P12 ' . str_replace(':', ' ', $this->operation) . ' maintenance.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions(['dry-run']);
        $dryRun = $input->requireFlag('dry-run');
        try {
            $result = match ($this->operation) {
                'outbox:publish' => $this->maintenance->processOutbox($dryRun),
                'notifications:deliver' => $this->maintenance->processNotifications($dryRun),
                'webhooks:deliver' => $this->maintenance->processWebhooks($dryRun),
                'work:reconcile' => $this->maintenance->reconcile(),
                'retention:process' => $this->maintenance->retention($dryRun),
                'operations:cleanup' => $this->maintenance->cleanup($dryRun),
                'audit:verify' => $this->maintenance->verifyAudit(),
                'backups:verify' => $this->maintenance->verifyBackups(),
                'webhooks:verify' => $this->maintenance->verifyWebhooks(),
                'restores:verify' => $this->maintenance->verifyRestores(),
                'privacy:verify', 'operations:verify' => $this->verify(),
                default => throw new \RuntimeException('Unknown P12 runtime operation.'),
            };
            $output->write("P12 runtime operation: PASS\nOperation: {$this->operation}\n"
                . "Examined: {$result['examined']}\nChanged: {$result['changed']}\n"
                . 'Dry run: ' . ($dryRun ? 'yes' : 'no') . "\n");
            return 0;
        } catch (\Throwable $error) {
            $output->write("P12 runtime operation: FAIL\nOperation: {$this->operation}\n{$error->getMessage()}\n");
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
