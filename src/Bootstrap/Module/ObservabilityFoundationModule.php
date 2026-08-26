<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Qmdb\Bootstrap\ApplicationMetadata;
use Qmdb\Shared\Configuration\ApplicationConfiguration;
use Qmdb\Shared\Configuration\Logging\LoggingConfiguration;
use Qmdb\Shared\Console\Observability\ConsoleExecutionObserver;
use Qmdb\Shared\DependencyInjection\ClosureServiceFactory;
use Qmdb\Shared\DependencyInjection\DependencyResolver;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\DependencyInjection\ServiceReference;
use Qmdb\Shared\Identifier\RuntimeIdentifierGenerator;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;
use Qmdb\Shared\Observability\Correlation\CorrelationIdGenerator;
use Qmdb\Shared\Observability\Correlation\SecureCorrelationIdGenerator;
use Qmdb\Shared\Observability\Error\BootstrapFailureReporter;
use Qmdb\Shared\Observability\Error\BootstrapFailureResponder;
use Qmdb\Shared\Observability\Error\ErrorHandlingRuntime;
use Qmdb\Shared\Observability\Error\ExceptionFingerprint;
use Qmdb\Shared\Observability\Error\FatalErrorShutdownReporter;
use Qmdb\Shared\Observability\Error\InternalErrorChannel;
use Qmdb\Shared\Observability\Error\LastErrorProvider;
use Qmdb\Shared\Observability\Error\NativeLastErrorProvider;
use Qmdb\Shared\Observability\Error\PhpErrorHandler;
use Qmdb\Shared\Observability\Error\StructuredThrowableReporter;
use Qmdb\Shared\Observability\Error\ThrowableReporter;
use Qmdb\Shared\Observability\Logging\EventLogger;
use Qmdb\Shared\Observability\Logging\LogContextSanitizer;
use Qmdb\Shared\Observability\Logging\MonologStructuredLoggerFactory;
use Qmdb\Shared\Observability\Logging\PsrEventLogger;
use Qmdb\Shared\Observability\Logging\ResilientLogger;
use Qmdb\Shared\Observability\Logging\SensitiveKeyMatcher;
use Qmdb\Shared\Observability\Logging\SensitiveValueRedactor;
use Qmdb\Shared\Observability\Logging\StaticApplicationContextProcessor;
use Qmdb\Shared\Observability\Logging\StructuredLoggerFactory;
use Qmdb\Shared\Time\MonotonicClock;
use Qmdb\Shared\Time\SystemMonotonicClock;

final readonly class ObservabilityFoundationModule implements Module
{
    private const ID = 'foundation.observability';

    public function __construct(private LoggingConfiguration $configuration)
    {
    }

    public function id(): ModuleId
    {
        return new ModuleId(self::ID);
    }

    public function dependencies(): array
    {
        return [new ModuleId('foundation.core'), new ModuleId('foundation.application')];
    }

    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::instance(
            LoggingConfiguration::class,
            self::ID,
            $this->configuration,
        ));
        $context->service(ServiceDefinition::factory(
            SensitiveKeyMatcher::class,
            self::ID,
            [],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): SensitiveKeyMatcher => new SensitiveKeyMatcher(),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            SensitiveValueRedactor::class,
            self::ID,
            [SensitiveKeyMatcher::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): SensitiveValueRedactor => new SensitiveValueRedactor(
                    ServiceReference::get($resolver, SensitiveKeyMatcher::class),
                ),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            LogContextSanitizer::class,
            self::ID,
            [],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): LogContextSanitizer => new LogContextSanitizer(),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            InternalErrorChannel::class,
            self::ID,
            [],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): InternalErrorChannel => new InternalErrorChannel(),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            StaticApplicationContextProcessor::class,
            self::ID,
            [ApplicationMetadata::class, ApplicationConfiguration::class],
            new ClosureServiceFactory(static function (
                DependencyResolver $resolver,
            ): StaticApplicationContextProcessor {
                $metadata = ServiceReference::get($resolver, ApplicationMetadata::class);
                $application = ServiceReference::get($resolver, ApplicationConfiguration::class);

                return new StaticApplicationContextProcessor([
                    'application' => $metadata->applicationCode(),
                    'environment' => $application->environment()->toSafeString(),
                    'phase' => $metadata->currentPhase(),
                    'batch' => $metadata->currentBatch(),
                    'baseline' => $metadata->frozenBaseline(),
                ]);
            }),
        ));
        $context->service(ServiceDefinition::factory(
            MonologStructuredLoggerFactory::class,
            self::ID,
            [LoggingConfiguration::class, StaticApplicationContextProcessor::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): MonologStructuredLoggerFactory =>
                    new MonologStructuredLoggerFactory(
                        ServiceReference::get($resolver, LoggingConfiguration::class),
                        ServiceReference::get($resolver, StaticApplicationContextProcessor::class),
                    ),
            ),
        ));
        $context->alias(StructuredLoggerFactory::class, MonologStructuredLoggerFactory::class);
        $context->service(ServiceDefinition::factory(
            Logger::class,
            self::ID,
            [StructuredLoggerFactory::class],
            new ClosureServiceFactory(static function (DependencyResolver $resolver): Logger {
                $logger = ServiceReference::get($resolver, StructuredLoggerFactory::class)->create();
                if (!$logger instanceof Logger) {
                    throw new \RuntimeException('Structured logger factory returned an invalid logger.');
                }

                return $logger;
            }),
        ));
        $context->service(ServiceDefinition::factory(
            ResilientLogger::class,
            self::ID,
            [Logger::class, SensitiveValueRedactor::class, LogContextSanitizer::class, InternalErrorChannel::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): ResilientLogger => new ResilientLogger(
                    ServiceReference::get($resolver, Logger::class),
                    ServiceReference::get($resolver, SensitiveValueRedactor::class),
                    ServiceReference::get($resolver, LogContextSanitizer::class),
                    ServiceReference::get($resolver, InternalErrorChannel::class),
                ),
            ),
        ));
        $context->alias(LoggerInterface::class, ResilientLogger::class);
        $context->service(ServiceDefinition::factory(
            PsrEventLogger::class,
            self::ID,
            [LoggerInterface::class, SensitiveValueRedactor::class, LogContextSanitizer::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): PsrEventLogger => new PsrEventLogger(
                    ServiceReference::get($resolver, LoggerInterface::class),
                    ServiceReference::get($resolver, SensitiveValueRedactor::class),
                    ServiceReference::get($resolver, LogContextSanitizer::class),
                ),
            ),
        ));
        $context->alias(EventLogger::class, PsrEventLogger::class);
        $context->service(ServiceDefinition::factory(
            SecureCorrelationIdGenerator::class,
            self::ID,
            [RuntimeIdentifierGenerator::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): SecureCorrelationIdGenerator =>
                    new SecureCorrelationIdGenerator(
                        ServiceReference::get($resolver, RuntimeIdentifierGenerator::class),
                    ),
            ),
        ));
        $context->alias(CorrelationIdGenerator::class, SecureCorrelationIdGenerator::class);
        $context->service(ServiceDefinition::factory(
            SystemMonotonicClock::class,
            self::ID,
            [],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): SystemMonotonicClock => new SystemMonotonicClock(),
            ),
        ));
        $context->alias(MonotonicClock::class, SystemMonotonicClock::class);
        $context->service(ServiceDefinition::factory(
            ExceptionFingerprint::class,
            self::ID,
            [],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): ExceptionFingerprint => new ExceptionFingerprint(),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            StructuredThrowableReporter::class,
            self::ID,
            [
                EventLogger::class,
                ExceptionFingerprint::class,
                SensitiveValueRedactor::class,
                LogContextSanitizer::class,
            ],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): StructuredThrowableReporter =>
                    new StructuredThrowableReporter(
                        ServiceReference::get($resolver, EventLogger::class),
                        ServiceReference::get($resolver, ExceptionFingerprint::class),
                        ServiceReference::get($resolver, SensitiveValueRedactor::class),
                        ServiceReference::get($resolver, LogContextSanitizer::class),
                    ),
            ),
        ));
        $context->alias(ThrowableReporter::class, StructuredThrowableReporter::class);
        $context->service(ServiceDefinition::factory(
            PhpErrorHandler::class,
            self::ID,
            [],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): PhpErrorHandler => new PhpErrorHandler(),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            NativeLastErrorProvider::class,
            self::ID,
            [],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): NativeLastErrorProvider => new NativeLastErrorProvider(),
            ),
        ));
        $context->alias(LastErrorProvider::class, NativeLastErrorProvider::class);
        $context->service(ServiceDefinition::factory(
            FatalErrorShutdownReporter::class,
            self::ID,
            [LastErrorProvider::class, EventLogger::class, CorrelationIdGenerator::class, ExceptionFingerprint::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): FatalErrorShutdownReporter =>
                    new FatalErrorShutdownReporter(
                        ServiceReference::get($resolver, LastErrorProvider::class),
                        ServiceReference::get($resolver, EventLogger::class),
                        ServiceReference::get($resolver, CorrelationIdGenerator::class),
                        ServiceReference::get($resolver, ExceptionFingerprint::class),
                    ),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            ErrorHandlingRuntime::class,
            self::ID,
            [PhpErrorHandler::class, FatalErrorShutdownReporter::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): ErrorHandlingRuntime => new ErrorHandlingRuntime(
                    ServiceReference::get($resolver, PhpErrorHandler::class),
                    ServiceReference::get($resolver, FatalErrorShutdownReporter::class),
                ),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            BootstrapFailureReporter::class,
            self::ID,
            [InternalErrorChannel::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): BootstrapFailureReporter => new BootstrapFailureReporter(
                    ServiceReference::get($resolver, InternalErrorChannel::class),
                ),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            BootstrapFailureResponder::class,
            self::ID,
            [CorrelationIdGenerator::class, BootstrapFailureReporter::class],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): BootstrapFailureResponder => new BootstrapFailureResponder(
                    ServiceReference::get($resolver, CorrelationIdGenerator::class),
                    ServiceReference::get($resolver, BootstrapFailureReporter::class),
                ),
            ),
        ));
        $context->service(ServiceDefinition::factory(
            ConsoleExecutionObserver::class,
            self::ID,
            [
                CorrelationIdGenerator::class,
                EventLogger::class,
                MonotonicClock::class,
                ThrowableReporter::class,
                ExceptionFingerprint::class,
            ],
            new ClosureServiceFactory(
                static fn (DependencyResolver $resolver): ConsoleExecutionObserver => new ConsoleExecutionObserver(
                    ServiceReference::get($resolver, CorrelationIdGenerator::class),
                    ServiceReference::get($resolver, EventLogger::class),
                    ServiceReference::get($resolver, MonotonicClock::class),
                    ServiceReference::get($resolver, ThrowableReporter::class),
                    ServiceReference::get($resolver, ExceptionFingerprint::class),
                ),
            ),
        ));
    }
}
