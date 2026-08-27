<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Shared\Background\Configuration\BackgroundExecutionConfiguration;
use Qmdb\Shared\Background\Console\ScheduleListConsoleCommand;
use Qmdb\Shared\Background\Console\ScheduleRunConsoleCommand;
use Qmdb\Shared\Background\Console\WorkerRunConsoleCommand;
use Qmdb\Shared\Background\Job\BackgroundJobExecutor;
use Qmdb\Shared\Background\Job\BackgroundJobFailureClassifier;
use Qmdb\Shared\Background\Job\BackgroundJobHandlerMap;
use Qmdb\Shared\Background\Job\BackgroundJobHandlerRegistry;
use Qmdb\Shared\Background\Job\ConservativeBackgroundJobFailureClassifier;
use Qmdb\Shared\Background\Scheduler\Infrastructure\MySqlScheduledTaskRunRepository;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskMap;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskRunRepository;
use Qmdb\Shared\Background\Scheduler\Scheduler;
use Qmdb\Shared\Background\Source\BackgroundJobSource;
use Qmdb\Shared\Background\Source\NullBackgroundJobSource;
use Qmdb\Shared\Background\Worker\BackgroundWorker;
use Qmdb\Shared\Background\Worker\BackgroundWorkerIdentityGenerator;
use Qmdb\Shared\Background\Worker\MemoryUsageProvider;
use Qmdb\Shared\Background\Worker\NativeMemoryUsageProvider;
use Qmdb\Shared\Background\Worker\NullWorkerSignalController;
use Qmdb\Shared\Background\Worker\PcntlWorkerSignalController;
use Qmdb\Shared\Background\Worker\WorkerSignalController;
use Qmdb\Shared\Configuration\ApplicationConfiguration;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Database\Transaction\Sleeper;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\DependencyInjection\ServiceReference;
use Qmdb\Shared\Identifier\RuntimeIdentifierGenerator;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;
use Qmdb\Shared\Observability\Correlation\CorrelationIdGenerator;
use Qmdb\Shared\Observability\Error\ExceptionFingerprint;
use Qmdb\Shared\Observability\Logging\EventLogger;
use Qmdb\Shared\Time\Clock;
use Qmdb\Shared\Time\MonotonicClock;

final readonly class BackgroundExecutionFoundationModule implements Module
{
    private const ID = 'foundation.background';

    public function __construct(private BackgroundExecutionConfiguration $configuration)
    {
    }

    public function id(): ModuleId
    {
        return new ModuleId(self::ID);
    }

    public function dependencies(): array
    {
        return [
            new ModuleId('foundation.core'),
            new ModuleId('foundation.application'),
            new ModuleId('foundation.observability'),
            new ModuleId('foundation.database'),
            new ModuleId('foundation.schema'),
        ];
    }

    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::instance(
            BackgroundExecutionConfiguration::class,
            self::ID,
            $this->configuration,
        ));
        $this->registerJobs($context);
        $this->registerWorker($context);
        $this->registerScheduler($context);
        $this->registerCommands($context);
    }

    private function registerJobs(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::instance(
            BackgroundJobHandlerMap::class,
            self::ID,
            (new BackgroundJobHandlerRegistry())->build(),
        ));
        $context->service(ServiceDefinition::instance(
            NullBackgroundJobSource::class,
            self::ID,
            new NullBackgroundJobSource(),
        ));
        $context->alias(BackgroundJobSource::class, NullBackgroundJobSource::class);
        $context->service(ServiceDefinition::factory(
            ConservativeBackgroundJobFailureClassifier::class,
            self::ID,
            [ExceptionFingerprint::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): ConservativeBackgroundJobFailureClassifier =>
                    new ConservativeBackgroundJobFailureClassifier(
                        ServiceReference::get($resolver, ExceptionFingerprint::class),
                    ),
            ),
        ));
        $context->alias(
            BackgroundJobFailureClassifier::class,
            ConservativeBackgroundJobFailureClassifier::class,
        );
        $context->service(ServiceDefinition::factory(
            BackgroundJobExecutor::class,
            self::ID,
            [
                BackgroundJobHandlerMap::class,
                BackgroundJobSource::class,
                BackgroundJobFailureClassifier::class,
                Clock::class,
                EventLogger::class,
            ],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): BackgroundJobExecutor =>
                new BackgroundJobExecutor(
                    ServiceReference::get($resolver, BackgroundJobHandlerMap::class),
                    ServiceReference::get($resolver, BackgroundJobSource::class),
                    ServiceReference::get($resolver, BackgroundJobFailureClassifier::class),
                    ServiceReference::get($resolver, Clock::class),
                    ServiceReference::get($resolver, EventLogger::class),
                )),
        ));
    }

    private function registerWorker(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(
            BackgroundWorkerIdentityGenerator::class,
            self::ID,
            [RuntimeIdentifierGenerator::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): BackgroundWorkerIdentityGenerator =>
                new BackgroundWorkerIdentityGenerator(
                    ServiceReference::get($resolver, RuntimeIdentifierGenerator::class),
                )),
        ));
        $signalController = function_exists('pcntl_signal')
            ? new PcntlWorkerSignalController()
            : new NullWorkerSignalController();
        $context->service(ServiceDefinition::instance(
            $signalController::class,
            self::ID,
            $signalController,
        ));
        $context->alias(WorkerSignalController::class, $signalController::class);
        $context->service(ServiceDefinition::instance(
            NativeMemoryUsageProvider::class,
            self::ID,
            new NativeMemoryUsageProvider(),
        ));
        $context->alias(MemoryUsageProvider::class, NativeMemoryUsageProvider::class);
        $context->service(ServiceDefinition::factory(
            BackgroundWorker::class,
            self::ID,
            [
                BackgroundJobSource::class,
                BackgroundJobExecutor::class,
                BackgroundWorkerIdentityGenerator::class,
                CorrelationIdGenerator::class,
                WorkerSignalController::class,
                MemoryUsageProvider::class,
                Sleeper::class,
                Clock::class,
                MonotonicClock::class,
                EventLogger::class,
            ],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): BackgroundWorker =>
                new BackgroundWorker(
                    ServiceReference::get($resolver, BackgroundJobSource::class),
                    ServiceReference::get($resolver, BackgroundJobExecutor::class),
                    ServiceReference::get($resolver, BackgroundWorkerIdentityGenerator::class),
                    ServiceReference::get($resolver, CorrelationIdGenerator::class),
                    ServiceReference::get($resolver, WorkerSignalController::class),
                    ServiceReference::get($resolver, MemoryUsageProvider::class),
                    ServiceReference::get($resolver, Sleeper::class),
                    ServiceReference::get($resolver, Clock::class),
                    ServiceReference::get($resolver, MonotonicClock::class),
                    ServiceReference::get($resolver, EventLogger::class),
                )),
        ));
    }

    private function registerScheduler(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(
            MySqlScheduledTaskRunRepository::class,
            self::ID,
            [DatabaseConnectionProvider::class, TransactionManager::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): MySqlScheduledTaskRunRepository =>
                new MySqlScheduledTaskRunRepository(
                    ServiceReference::get($resolver, DatabaseConnectionProvider::class),
                    ServiceReference::get($resolver, TransactionManager::class),
                )),
        ));
        $context->alias(ScheduledTaskRunRepository::class, MySqlScheduledTaskRunRepository::class);
        $context->service(ServiceDefinition::factory(
            Scheduler::class,
            self::ID,
            [
                ScheduledTaskMap::class,
                ScheduledTaskRunRepository::class,
                RuntimeIdentifierGenerator::class,
                CorrelationIdGenerator::class,
                ExceptionFingerprint::class,
                Clock::class,
                MonotonicClock::class,
                EventLogger::class,
            ],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): Scheduler => new Scheduler(
                ServiceReference::get($resolver, ScheduledTaskMap::class),
                ServiceReference::get($resolver, ScheduledTaskRunRepository::class),
                ServiceReference::get($resolver, RuntimeIdentifierGenerator::class),
                ServiceReference::get($resolver, CorrelationIdGenerator::class),
                ServiceReference::get($resolver, ExceptionFingerprint::class),
                ServiceReference::get($resolver, Clock::class),
                ServiceReference::get($resolver, MonotonicClock::class),
                ServiceReference::get($resolver, EventLogger::class),
            )),
        ));
    }

    private function registerCommands(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::factory(
            WorkerRunConsoleCommand::class,
            self::ID,
            [
                BackgroundWorker::class,
                BackgroundExecutionConfiguration::class,
                ApplicationConfiguration::class,
                WorkerSignalController::class,
            ],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): WorkerRunConsoleCommand =>
                new WorkerRunConsoleCommand(
                    ServiceReference::get($resolver, BackgroundWorker::class),
                    ServiceReference::get($resolver, BackgroundExecutionConfiguration::class),
                    ServiceReference::get($resolver, ApplicationConfiguration::class)->environment(),
                    ServiceReference::get($resolver, WorkerSignalController::class),
                )),
        ));
        $context->service(ServiceDefinition::factory(
            ScheduleListConsoleCommand::class,
            self::ID,
            [ScheduledTaskMap::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): ScheduleListConsoleCommand =>
                new ScheduleListConsoleCommand(ServiceReference::get($resolver, ScheduledTaskMap::class))),
        ));
        $context->service(ServiceDefinition::factory(
            ScheduleRunConsoleCommand::class,
            self::ID,
            [Scheduler::class],
            new ClosureServiceFactory(static fn (DependencyResolver $resolver): ScheduleRunConsoleCommand =>
                new ScheduleRunConsoleCommand(ServiceReference::get($resolver, Scheduler::class))),
        ));
    }
}
