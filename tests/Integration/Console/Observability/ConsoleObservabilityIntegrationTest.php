<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\Console\Observability;

use PHPUnit\Framework\TestCase;
use Qmdb\Bootstrap\Application;
use Qmdb\Bootstrap\ApplicationMetadata;
use Qmdb\Bootstrap\Console\ConsoleApplication;
use Qmdb\Bootstrap\RuntimeRequirements;
use Qmdb\Bootstrap\Shared\ExitCode;
use Qmdb\Shared\Application\Command\Command;
use Qmdb\Shared\Application\Command\CommandBus;
use Qmdb\Shared\Application\Event\DomainEventDispatcher;
use Qmdb\Shared\Application\Query\Query;
use Qmdb\Shared\Application\Query\QueryBus;
use Qmdb\Shared\Configuration\ApplicationConfigurationFactory;
use Qmdb\Shared\Configuration\ConfigurationSource;
use Qmdb\Shared\Configuration\EnvironmentVariables;
use Qmdb\Shared\Console\Observability\ConsoleExecutionObserver;
use Qmdb\Shared\Domain\Event\DomainEvent;
use Qmdb\Shared\Observability\Error\ExceptionFingerprint;
use Qmdb\Tests\Support\ApplicationTestFactory;
use Qmdb\Tests\Support\Observability\FakeMonotonicClock;
use Qmdb\Tests\Support\Observability\InMemoryEventLogger;
use Qmdb\Tests\Support\Observability\RecordingThrowableReporter;
use Qmdb\Tests\Support\Observability\SequenceCorrelationIdGenerator;
use RuntimeException;

final class ConsoleObservabilityIntegrationTest extends TestCase
{
    public function testSuccessfulCommandUsesOnePrivateExecutionIdentifier(): void
    {
        $events = new InMemoryEventLogger();
        $requestId = str_repeat('a', 32);
        $console = new ConsoleApplication(
            ApplicationTestFactory::create(),
            executionObserver: $this->observer($events, [$requestId], [100, 2_000_100]),
        );

        $result = $console->run(['app:about'], '8.5.0', ['json', 'mbstring']);
        $encoded = json_encode($events->records(), JSON_THROW_ON_ERROR);

        self::assertSame(ExitCode::SUCCESS, $result->exitCode());
        self::assertStringNotContainsString($requestId, $result->standardOutput());
        self::assertSame(['console.command.started', 'console.command.completed'], array_column(
            $events->records(),
            'event',
        ));
        self::assertSame($requestId, $events->records()[0]['context']['request_id']);
        self::assertSame($requestId, $events->records()[1]['context']['request_id']);
        self::assertStringNotContainsString('secret', strtolower($encoded));
    }

    public function testUnexpectedFailureReturnsAndLogsSameSafeReference(): void
    {
        $events = new InMemoryEventLogger();
        $reporter = new RecordingThrowableReporter();
        $requestId = str_repeat('b', 32);
        $observer = new ConsoleExecutionObserver(
            new SequenceCorrelationIdGenerator([$requestId]),
            $events,
            new FakeMonotonicClock([1, 2]),
            $reporter,
            new ExceptionFingerprint(),
        );
        $console = new ConsoleApplication($this->failingApplication(), executionObserver: $observer);

        $result = $console->run(['app:about'], '8.5.0', ['json', 'mbstring']);
        $combined = $result->standardOutput() . $result->standardError()
            . json_encode($events->records(), JSON_THROW_ON_ERROR);

        self::assertSame(ExitCode::FAILURE, $result->exitCode());
        self::assertSame("The operation could not be completed.\nReference: $requestId\n", $result->standardError());
        self::assertSame($requestId, $reporter->reports()[0]['request_id']);
        self::assertSame(['console.command.started', 'console.command.failed'], array_column(
            $events->records(),
            'event',
        ));
        self::assertStringNotContainsString('QMDB_CONSOLE_EXCEPTION_SECRET', $combined);
        self::assertStringNotContainsString(__FILE__, $combined);
    }

    /** @param list<string> $ids
     *  @param list<int> $times
     */
    private function observer(InMemoryEventLogger $events, array $ids, array $times): ConsoleExecutionObserver
    {
        return new ConsoleExecutionObserver(
            new SequenceCorrelationIdGenerator($ids),
            $events,
            new FakeMonotonicClock($times),
            new RecordingThrowableReporter(),
            new ExceptionFingerprint(),
        );
    }

    private function failingApplication(): Application
    {
        $configuration = (new ApplicationConfigurationFactory())->create(
            new EnvironmentVariables([
                'APP_ENV' => 'test',
                'APP_DEBUG' => 'false',
                'APP_TIMEZONE' => 'UTC',
            ]),
            ConfigurationSource::PROCESS,
        );
        $queryBus = new class implements QueryBus {
            public function ask(Query $query): mixed
            {
                throw new RuntimeException('QMDB_CONSOLE_EXCEPTION_SECRET at ' . __FILE__);
            }
        };
        $commandBus = new class implements CommandBus {
            public function dispatch(Command $command): void
            {
            }
        };
        $events = new class implements DomainEventDispatcher {
            public function dispatch(DomainEvent $event): void
            {
            }
        };

        return new Application(
            ApplicationMetadata::current(),
            new RuntimeRequirements(),
            $configuration,
            $queryBus,
            $commandBus,
            $events,
        );
    }
}
