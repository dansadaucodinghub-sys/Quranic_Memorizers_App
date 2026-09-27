<?php

declare(strict_types=1);

namespace Qmdb\Modules\PilotOfflineRollout\Interface\Console;

use Qmdb\Modules\PilotOfflineRollout\Domain\OfflinePackageCryptography;
use Qmdb\Modules\PilotOfflineRollout\Infrastructure\Persistence\MySqlPilotOfflineRolloutRepository;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskMap;
use Qmdb\Shared\Configuration\ApplicationConfiguration;
use Qmdb\Shared\Configuration\ConfigurationSource;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;

readonly class CompetitionP13VerifyConsoleCommand implements ConsoleCommand
{
    public function __construct(
        private string $command,
        private MySqlPilotOfflineRolloutRepository $repository,
        private ScheduledTaskMap $tasks,
        private OfflinePackageCryptography $cryptography,
        private ApplicationConfiguration $application,
    ) {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName($this->command);
    }

    public function description(): string
    {
        return 'Verify P13 pilot, secure offline venue, synchronization, and rollout foundations.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $readiness = $this->command === 'competition:p13:production-readiness:verify';
        $input->assertOnlyOptions($readiness ? ['production-like'] : []);
        $strict = $readiness && $input->requireFlag('production-like');
        try {
            $inventory = $this->repository->verifyFoundation();
            $required = ['pilot.readiness.reconcile', 'pilot.health.snapshot', 'rollout.waves.process', 'rollout.health.snapshot', 'offline.packages.process', 'offline.packages.expire', 'offline.packages.reconcile', 'offline.devices.reconcile', 'offline.sync.reconcile', 'offline.conflicts.notify', 'offline.receipts.reconcile'];
            $registered = array_map(static fn ($task): string => $task->id()->value(), $this->tasks->tasks());
            foreach ($required as $task) {
                if (!in_array($task, $registered, true)) {
                    throw new \RuntimeException('Required P13 scheduled task is missing: ' . $task);
                }
            }
            if ($this->application->debugEnabled()) {
                throw new \RuntimeException('Debug mode is enabled.');
            }
            if ($strict && (!$this->application->isProductionLike() || $this->application->source() !== ConfigurationSource::PROCESS)) {
                throw new \RuntimeException('Strict readiness requires externally injected staging or production configuration.');
            }
            if ($strict && !$this->cryptography->productionConfigured()) {
                throw new \RuntimeException('Strict readiness requires externally injected P13 signing and encryption keys.');
            }
            $message = "Competition P13 verification: PASS\nScope: Pilot, Secure Offline Venue, Synchronisation, and National Rollout\n";
            foreach ($inventory as $name => $count) {
                $message .= ucfirst($name) . ': ' . $count . "\n";
            }
            $message .= 'Scheduler tasks: ' . count($required) . "\nCryptographic provider: " . ($this->cryptography->productionConfigured() ? 'production-configured' : 'deterministic-local-test-only') . "\n";
            if ($readiness) {
                $message .= 'Readiness mode: ' . ($strict ? 'production-like' : 'predeployment') . "\n";
                $message .= "Test cryptographic providers in production-like mode: rejected\n";
            }
            if ($this->command === 'competition:p13:production-smoke:verify') {
                $message .= "Smoke mode: non-mutating; no pilot, device, package, offline change, conflict decision, or rollout activation performed.\n";
            }
            $output->write($message);

            return 0;
        } catch (\Throwable $error) {
            $output->write("Competition P13 verification: FAIL\n{$error->getMessage()}\n");

            return 1;
        }
    }
}
