<?php

declare(strict_types=1);

namespace Qmdb\Modules\PilotOfflineRollout\Interface\Console;

use Qmdb\Modules\PilotOfflineRollout\Domain\OfflinePackageCryptography;
use Qmdb\Modules\PilotOfflineRollout\Application\OfflineOperationDispatcher;
use Qmdb\Modules\PilotOfflineRollout\Application\OfflineOperationHandlerCatalog;
use Qmdb\Modules\PilotOfflineRollout\Application\P13AdministrativeMutationService;
use Qmdb\Modules\PilotOfflineRollout\Application\P13MaintenanceService;
use Qmdb\Modules\PilotOfflineRollout\Application\P13RouteRuntimeCatalog;
use Qmdb\Modules\PilotOfflineRollout\Application\P13RuntimeCatalogVerifier;
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
        private OfflineOperationDispatcher $dispatcher,
        private P13RouteRuntimeCatalog $routes,
        private P13MaintenanceService $maintenance,
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
            $routeEntries = $this->routes->entries();
            $runtimeCatalogs = new P13RuntimeCatalogVerifier();
            $runtimeCatalogs->verifyRoutes($routeEntries);
            $browserMutations = P13AdministrativeMutationService::SUPPORTED_ROUTES;
            sort($browserMutations);
            $expectedBrowserMutations = P13RouteRuntimeCatalog::BROWSER_MUTATIONS;
            sort($expectedBrowserMutations);
            if ($browserMutations !== $expectedBrowserMutations) {
                throw new \RuntimeException('A P13 browser mutation lacks a concrete application handler.');
            }
            $handlers = $this->dispatcher->registrations();
            if (count($handlers) !== 13 || count(array_filter($handlers, static fn (string $domain): bool => $domain === 'P7_LIVE')) !== 8 || count(array_filter($handlers, static fn (string $domain): bool => $domain === 'P6_SCORING')) !== 2 || count(array_filter($handlers, static fn (string $domain): bool => $domain === 'P13_OFFLINE')) !== 3) {
                throw new \RuntimeException('P13 authoritative offline handler map is incomplete.');
            }
            $handlerCatalog = (new OfflineOperationHandlerCatalog())->entries();
            $runtimeCatalogs->verifyHandlers($handlerCatalog, $handlers);
            $required = ['pilot.readiness.reconcile', 'pilot.health.snapshot', 'rollout.waves.process', 'rollout.health.snapshot', 'offline.packages.process', 'offline.packages.expire', 'offline.packages.reconcile', 'offline.devices.reconcile', 'offline.sync.reconcile', 'offline.conflicts.notify', 'offline.receipts.reconcile'];
            $registered = array_map(static fn ($task): string => $task->id()->value(), $this->tasks->tasks());
            foreach ($required as $task) {
                if (!in_array($task, $registered, true)) {
                    throw new \RuntimeException('Required P13 scheduled task is missing: ' . $task);
                }
            }
            $maintenanceHandlers = $this->maintenance->handlers();
            if (count($maintenanceHandlers) !== 17 || in_array('verifyRuntime', $maintenanceHandlers, true)) {
                throw new \RuntimeException('P13 maintenance contains a generic or incomplete runtime handler.');
            }
            foreach ($maintenanceHandlers as $operation => $method) {
                if (!method_exists(MySqlPilotOfflineRolloutRepository::class, $method) || !(new \ReflectionMethod(MySqlPilotOfflineRolloutRepository::class, $method))->isPublic()) {
                    throw new \RuntimeException('P13 maintenance handler is unavailable: ' . $operation);
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
            $message .= 'Routes: ' . count($routeEntries) . "\nBrowser mutation handlers: " . count($expectedBrowserMutations) . "\nOffline operation handlers: " . count($handlers) . "\n";
            $message .= 'Scheduler tasks: ' . count($required) . "\nMaintenance handlers: " . count($maintenanceHandlers) . "\nCryptographic provider: " . ($this->cryptography->productionConfigured() ? 'production-configured' : 'deterministic-local-test-only') . "\n";
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
