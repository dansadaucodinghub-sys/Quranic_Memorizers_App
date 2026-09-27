<?php

declare(strict_types=1);

namespace Qmdb\Modules\SearchAnalytics\Interface\Console;

use Qmdb\Modules\SearchAnalytics\Infrastructure\Persistence\MySqlP11Repository;
use Qmdb\Modules\SearchAnalytics\Infrastructure\Persistence\PrivateExportArtifactStore;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskMap;
use Qmdb\Shared\Configuration\ApplicationConfiguration;
use Qmdb\Shared\Configuration\ConfigurationSource;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;

final readonly class CompetitionP11ProductionReadinessConsoleCommand implements ConsoleCommand
{
    private const array TASKS = [
        'analytics.snapshots.process', 'analytics.snapshots.reconcile', 'reports.process',
        'reports.reconcile', 'exports.cleanup',
    ];

    public function __construct(
        private MySqlP11Repository $repository,
        private ScheduledTaskMap $tasks,
        private ApplicationConfiguration $application,
        private PrivateExportArtifactStore $artifacts,
    ) {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('competition:p11:production-readiness:verify');
    }

    public function description(): string
    {
        return 'Verify the P11 predeployment contract without deploying.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions(['production-like']);
        $strict = $input->requireFlag('production-like');
        try {
            $inventory = $this->repository->verifyFoundation();
            $registered = array_map(static fn ($task): string => $task->id()->value(), $this->tasks->tasks());
            foreach (self::TASKS as $task) {
                if (!in_array($task, $registered, true)) {
                    throw new \RuntimeException('Required P11 scheduler task is missing: ' . $task);
                }
            }
            if ($this->application->debugEnabled()) {
                throw new \RuntimeException('Debug mode is enabled.');
            }
            if ($strict && (!$this->application->isProductionLike() || $this->application->source() !== ConfigurationSource::PROCESS)) {
                throw new \RuntimeException('Strict readiness requires externally injected staging or production configuration.');
            }
            $root = $this->artifacts->root();
            if (str_contains(str_replace('\\', '/', $root), '/public/')) {
                throw new \RuntimeException('Private export storage is under the public document root.');
            }
            $scope = $strict ? 'production-like' : 'predeployment';
            $output->write("Competition P11 {$scope} readiness: PASS\n"
                . "Required tables: {$inventory['tables']}\nRequired schedulers: " . count(self::TASKS) . "\n"
                . "Private artifact storage: configured outside public root\n"
                . "Offline venue operations: NOT MAPPED TO P11\n");
            return 0;
        } catch (\Throwable $error) {
            $output->write("Competition P11 production readiness: FAIL\n{$error->getMessage()}\n");
            return 1;
        }
    }
}
