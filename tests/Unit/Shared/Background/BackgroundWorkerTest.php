<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Background;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Background\Job\BackgroundJobEnvelope;
use Qmdb\Shared\Background\Job\BackgroundJobExecutor;
use Qmdb\Shared\Background\Job\BackgroundJobHandlerMap;
use Qmdb\Shared\Background\Job\BackgroundJobId;
use Qmdb\Shared\Background\Job\BackgroundJobName;
use Qmdb\Shared\Background\Job\ConservativeBackgroundJobFailureClassifier;
use Qmdb\Shared\Background\Job\JobReservationToken;
use Qmdb\Shared\Background\Job\ReservedBackgroundJob;
use Qmdb\Shared\Background\Configuration\BackgroundExecutionConfiguration;
use Qmdb\Shared\Background\Console\WorkerRunConsoleCommand;
use Qmdb\Shared\Background\Worker\BackgroundWorker;
use Qmdb\Shared\Background\Worker\BackgroundWorkerIdentityGenerator;
use Qmdb\Shared\Background\Worker\BackgroundWorkerOptions;
use Qmdb\Shared\Background\Worker\BackgroundWorkerStopReason;
use Qmdb\Shared\Background\Worker\NullWorkerSignalController;
use Qmdb\Shared\Configuration\ApplicationEnvironment;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Input\ConsoleOption;
use Qmdb\Shared\Console\Input\ConsoleOptionName;
use Qmdb\Shared\Console\Output\BufferedConsoleOutput;
use Qmdb\Shared\Observability\Correlation\CorrelationId;
use Qmdb\Shared\Observability\Error\ExceptionFingerprint;
use Qmdb\Shared\Observability\Logging\EventLogger;
use Qmdb\Shared\Observability\Logging\LogEventName;
use Qmdb\Shared\Configuration\Logging\LogLevel;
use Qmdb\Tests\Support\Background\FakeMemoryUsageProvider;
use Qmdb\Tests\Support\Background\FakeSignalController;
use Qmdb\Tests\Support\Background\InMemoryBackgroundJobSource;
use Qmdb\Tests\Support\Background\RecordingSleeper;
use Qmdb\Tests\Support\Background\SequenceClock;
use Qmdb\Tests\Support\Background\SequenceRuntimeIdentifierGenerator;
use Qmdb\Tests\Support\Background\TestBackgroundJob;
use Qmdb\Tests\Support\Background\TestBackgroundJobHandler;
use Qmdb\Tests\Support\Observability\FakeMonotonicClock;
use Qmdb\Tests\Support\Observability\InMemoryEventLogger;
use Qmdb\Tests\Support\Observability\SequenceCorrelationIdGenerator;
use RuntimeException;

final class BackgroundWorkerTest extends TestCase
{
    private const NOW = '2026-08-25T09:00:00+00:00';

    public function testRunOnceStopsSafelyWhenNoWorkExists(): void
    {
        $signals = new FakeSignalController();
        $result = $this->worker(
            new InMemoryBackgroundJobSource(),
            $signals,
            new RecordingSleeper(),
            [0, 1],
        )->run($this->options(runOnce: true));

        self::assertSame(BackgroundWorkerStopReason::NO_WORK_ONCE, $result->stopReason());
        self::assertSame(0, $result->processedCount());
        self::assertSame(str_repeat('d', 32), $result->worker()->value());
        self::assertSame(str_repeat('e', 32), $result->correlationId()->value());
        self::assertTrue($signals->registered);
        self::assertTrue($signals->restored);
    }

    public function testOneJobIsProcessedThenMaximumJobBoundStopsWorker(): void
    {
        $source = new InMemoryBackgroundJobSource([$this->reserved()]);
        $result = $this->worker($source, new FakeSignalController(), new RecordingSleeper(), [0, 1, 2, 3])
            ->run($this->options(maximumJobs: 1));

        self::assertSame(BackgroundWorkerStopReason::MAX_JOBS, $result->stopReason());
        self::assertSame(1, $result->processedCount());
        self::assertSame(['acknowledge'], $source->actions);
    }

    public function testSignalRequestedDuringIdleSleepStopsGracefully(): void
    {
        $signals = new FakeSignalController();
        $sleeper = new RecordingSleeper($signals);
        $result = $this->worker(new InMemoryBackgroundJobSource(), $signals, $sleeper, [0, 1])
            ->run($this->options());

        self::assertSame(BackgroundWorkerStopReason::SIGNAL, $result->stopReason());
        self::assertSame([10], $sleeper->delays);
    }

    public function testSignalDuringAJobStopsOnlyAfterTheCurrentJobIsAcknowledged(): void
    {
        $signals = new FakeSignalController();
        $source = new InMemoryBackgroundJobSource([$this->reserved()]);
        $handler = new TestBackgroundJobHandler(
            duringExecution: static function () use ($signals): void {
                $signals->requestStop();
            },
        );
        $result = $this->worker(
            $source,
            $signals,
            new RecordingSleeper(),
            [0, 1],
            handler: $handler,
        )->run($this->options());

        self::assertSame(BackgroundWorkerStopReason::SIGNAL, $result->stopReason());
        self::assertSame(1, $result->processedCount());
        self::assertSame(['acknowledge'], $source->actions);
    }

    public function testLoggingFailureCannotCorruptSafeOnceModeStop(): void
    {
        $logger = new class implements EventLogger {
            public function log(LogLevel $level, LogEventName $event, array $context = []): void
            {
                throw new RuntimeException('QMDB_LOGGING_FAILURE_SECRET');
            }
        };
        $result = $this->worker(
            new InMemoryBackgroundJobSource(),
            new FakeSignalController(),
            new RecordingSleeper(),
            [0, 1],
            logger: $logger,
        )->run($this->options(runOnce: true));

        self::assertSame(BackgroundWorkerStopReason::NO_WORK_ONCE, $result->stopReason());
        self::assertTrue($result->isSuccessful());
    }

    /**
     * @param list<int> $monotonicValues
     * @param list<int> $memoryValues
     */
    #[DataProvider('boundedStops')]
    public function testRuntimeAndMemoryBoundsStopBeforeReservation(
        array $monotonicValues,
        array $memoryValues,
        BackgroundWorkerStopReason $expected,
    ): void {
        $source = new InMemoryBackgroundJobSource();
        $result = $this->worker(
            $source,
            new FakeSignalController(),
            new RecordingSleeper(),
            $monotonicValues,
            $memoryValues,
        )->run($this->options());

        self::assertSame($expected, $result->stopReason());
        self::assertSame(0, $source->reserveCalls);
    }

    /** @return iterable<string, array{list<int>, list<int>, BackgroundWorkerStopReason}> */
    public static function boundedStops(): iterable
    {
        yield 'runtime' => [[0, 2_000_000_000], [0], BackgroundWorkerStopReason::MAX_RUNTIME];
        yield 'memory' => [[0, 1], [64 * 1_048_576], BackgroundWorkerStopReason::MAX_MEMORY];
    }

    public function testSourceFailureIsReportedWithoutLeakingItsMessage(): void
    {
        $logger = new InMemoryEventLogger();
        $result = $this->worker(
            new InMemoryBackgroundJobSource(failReserve: true),
            new FakeSignalController(),
            new RecordingSleeper(),
            [0, 1],
            [0],
            $logger,
        )->run($this->options());

        self::assertSame(BackgroundWorkerStopReason::SOURCE_FAILURE, $result->stopReason());
        self::assertFalse($result->isSuccessful());
        self::assertStringNotContainsString(
            'QMDB_SOURCE_FAILURE_SECRET',
            json_encode($logger->records(), JSON_THROW_ON_ERROR),
        );
    }

    public function testProductionContinuousModeFailsClosedWithoutPcntl(): void
    {
        $signals = new NullWorkerSignalController();
        $command = new WorkerRunConsoleCommand(
            $this->worker(new InMemoryBackgroundJobSource(), new FakeSignalController(), new RecordingSleeper(), [0]),
            new BackgroundExecutionConfiguration(10, 10, 10, 32, true, 30, 1),
            ApplicationEnvironment::PRODUCTION,
            $signals,
        );
        $output = new BufferedConsoleOutput();

        self::assertSame(1, $command->execute(
            new ConsoleInput(new ConsoleCommandName('worker:run'), []),
            $output,
        ));
        self::assertStringContainsString('signal support is required', $output->standardError());
    }

    public function testProductionOnceModeDoesNotRequirePcntl(): void
    {
        $command = new WorkerRunConsoleCommand(
            $this->worker(
                new InMemoryBackgroundJobSource(),
                new FakeSignalController(),
                new RecordingSleeper(),
                [0, 1],
            ),
            new BackgroundExecutionConfiguration(10, 10, 10, 32, true, 30, 1),
            ApplicationEnvironment::PRODUCTION,
            new NullWorkerSignalController(),
        );
        $output = new BufferedConsoleOutput();
        $input = new ConsoleInput(new ConsoleCommandName('worker:run'), [
            new ConsoleOption(new ConsoleOptionName('once'), null),
        ]);

        self::assertSame(0, $command->execute($input, $output));
        self::assertStringContainsString('NO_WORK_ONCE', $output->standardOutput());
    }

    /**
     * @param list<int> $monotonicValues
     * @param list<int> $memoryValues
     */
    private function worker(
        InMemoryBackgroundJobSource $source,
        FakeSignalController $signals,
        RecordingSleeper $sleeper,
        array $monotonicValues,
        array $memoryValues = [0, 0, 0],
        ?EventLogger $logger = null,
        ?TestBackgroundJobHandler $handler = null,
    ): BackgroundWorker {
        $logger ??= new InMemoryEventLogger();
        $handler ??= new TestBackgroundJobHandler();

        return new BackgroundWorker(
            $source,
            new BackgroundJobExecutor(
                new BackgroundJobHandlerMap([TestBackgroundJob::class => $handler]),
                $source,
                new ConservativeBackgroundJobFailureClassifier(new ExceptionFingerprint()),
                new SequenceClock(array_fill(0, 8, new DateTimeImmutable(self::NOW))),
                $logger,
            ),
            new BackgroundWorkerIdentityGenerator(
                new SequenceRuntimeIdentifierGenerator([str_repeat('d', 32)]),
            ),
            new SequenceCorrelationIdGenerator([str_repeat('e', 32)]),
            $signals,
            new FakeMemoryUsageProvider($memoryValues),
            $sleeper,
            new SequenceClock(array_fill(0, 8, new DateTimeImmutable(self::NOW))),
            new FakeMonotonicClock($monotonicValues),
            $logger,
        );
    }

    private function options(
        bool $runOnce = false,
        int $maximumJobs = 10,
    ): BackgroundWorkerOptions {
        return new BackgroundWorkerOptions($runOnce, $maximumJobs, 1, 10, 32);
    }

    private function reserved(): ReservedBackgroundJob
    {
        $now = new DateTimeImmutable(self::NOW);

        return new ReservedBackgroundJob(
            new BackgroundJobEnvelope(
                new BackgroundJobId(str_repeat('a', 32)),
                new BackgroundJobName('test.background.job'),
                new TestBackgroundJob(),
                new CorrelationId(str_repeat('b', 32)),
                $now,
                $now,
            ),
            new JobReservationToken('opaque-reservation-token'),
        );
    }
}
