<?php

declare(strict_types=1);

namespace Qmdb\Modules\ProductionHardening\Interface\Console;

use Qmdb\Modules\ProductionHardening\Domain\IntegrationSecretBox;
use Qmdb\Modules\ProductionHardening\Infrastructure\Persistence\MySqlProductionHardeningRepository;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskMap;
use Qmdb\Shared\Configuration\ApplicationConfiguration;
use Qmdb\Shared\Configuration\ConfigurationSource;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;

final readonly class CompetitionP12ProductionReadinessConsoleCommand implements ConsoleCommand
{
    private const array TASKS = [
        'outbox.publish', 'notifications.deliver', 'webhooks.deliver', 'p12.work.reconcile',
        'privacy.retention.process', 'operations.cleanup', 'audit.lineage.verify', 'backups.metadata.verify',
    ];

    public function __construct(
        private MySqlProductionHardeningRepository $repository,
        private ScheduledTaskMap $tasks,
        private ApplicationConfiguration $application,
        private IntegrationSecretBox $secrets,
    ) {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('competition:p12:production-readiness:verify');
    }

    public function description(): string
    {
        return 'Verify the P12 predeployment contract without deployment or provider calls.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions(['production-like']);
        $strict = $input->requireFlag('production-like');
        try {
            $inventory = $this->repository->verifyFoundation();
            $summary = $this->repository->operationalSummary();
            $registered = array_map(static fn ($task): string => $task->id()->value(), $this->tasks->tasks());
            foreach (self::TASKS as $task) {
                if (!in_array($task, $registered, true)) {
                    throw new \RuntimeException('Required P12 scheduler task is missing: ' . $task);
                }
            }
            if ($this->application->debugEnabled()) {
                throw new \RuntimeException('Debug mode is enabled.');
            }
            if ($strict && (!$this->application->isProductionLike() || $this->application->source() !== ConfigurationSource::PROCESS)) {
                throw new \RuntimeException('Strict readiness requires externally injected staging or production configuration.');
            }
            if ($summary['webhooks_active'] > 0 && !$this->secrets->configured()) {
                throw new \RuntimeException('Active webhooks require P12 integration encryption configuration.');
            }
            $scope = $strict ? 'production-like' : 'predeployment';
            $output->write("Competition P12 {$scope} readiness: PASS\n"
                . "Required tables: {$inventory['tables']}\nRequired schedulers: " . count(self::TASKS) . "\n"
                . 'Integration encryption: ' . ($this->secrets->configured() ? 'configured' : 'not required without active subscriptions') . "\n"
                . "Test adapters in production: rejected\nPilot and offline operations: NOT MAPPED TO P12\n");
            return 0;
        } catch (\Throwable $error) {
            $output->write("Competition P12 production readiness: FAIL\n{$error->getMessage()}\n");
            return 1;
        }
    }
}
