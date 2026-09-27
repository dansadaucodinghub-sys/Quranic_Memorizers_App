<?php

declare(strict_types=1);

namespace Qmdb\Modules\ProductionHardening\Interface\Console;

use Qmdb\Modules\ProductionHardening\Infrastructure\Persistence\MySqlProductionHardeningRepository;
use Qmdb\Modules\ProductionHardening\Infrastructure\Persistence\P12MaintenanceService;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;

final readonly class CompetitionP12ProductionSmokeConsoleCommand implements ConsoleCommand
{
    public function __construct(private MySqlProductionHardeningRepository $repository, private P12MaintenanceService $maintenance)
    {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('competition:p12:production-smoke:verify');
    }

    public function description(): string
    {
        return 'Run isolated read-only and dry-run P12 production contract smoke checks.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        try {
            $this->repository->verifyFoundation();
            $plan = $this->repository->verifyDueQueryPlan();
            $this->maintenance->processOutbox(true);
            $this->maintenance->processNotifications(true);
            $this->maintenance->processWebhooks(true);
            $this->maintenance->retention(true);
            $this->maintenance->cleanup(true);
            $this->maintenance->verifyAudit();
            $this->maintenance->verifyBackups();
            $this->maintenance->verifyWebhooks();
            $this->maintenance->verifyRestores();
            $output->write("Competition P12 production-like smoke: PASS\nMode: isolated read-only and dry-run smoke\n"
                . "Webhook due-work index: {$plan['key']}\nWebhook access type: {$plan['type']}\n"
                . "External providers called: no\nReal notifications sent: no\nReal data deleted: no\n");
            return 0;
        } catch (\Throwable $error) {
            $output->write("Competition P12 production-like smoke: FAIL\n{$error->getMessage()}\n");
            return 1;
        }
    }
}
