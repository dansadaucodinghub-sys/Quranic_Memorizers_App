<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Background;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Background\Job\BackgroundJobEnvelope;
use Qmdb\Shared\Background\Job\BackgroundJobExecutionOutcome;
use Qmdb\Shared\Background\Job\BackgroundJobExecutor;
use Qmdb\Shared\Background\Job\BackgroundJobHandlerMap;
use Qmdb\Shared\Background\Job\BackgroundJobId;
use Qmdb\Shared\Background\Job\BackgroundJobName;
use Qmdb\Shared\Background\Job\ConservativeBackgroundJobFailureClassifier;
use Qmdb\Shared\Background\Job\JobReservationToken;
use Qmdb\Shared\Background\Job\PermanentBackgroundJobFailure;
use Qmdb\Shared\Background\Job\ReservedBackgroundJob;
use Qmdb\Shared\Background\Job\RetryableBackgroundJobFailure;
use Qmdb\Shared\Background\Worker\BackgroundWorkerIdentity;
use Qmdb\Shared\Observability\Correlation\CorrelationId;
use Qmdb\Shared\Observability\Error\ExceptionFingerprint;
use Qmdb\Tests\Support\Background\InMemoryBackgroundJobSource;
use Qmdb\Tests\Support\Background\SequenceClock;
use Qmdb\Tests\Support\Background\TestBackgroundJob;
use Qmdb\Tests\Support\Background\TestBackgroundJobHandler;
use Qmdb\Tests\Support\Observability\InMemoryEventLogger;
use RuntimeException;

final class BackgroundJobExecutionTest extends TestCase
{
    private const NOW = '2026-08-25T09:00:00+00:00';

    public function testSuccessfulJobIsAcknowledgedAfterExactHandlerReturnsNull(): void
    {
        $handler = new TestBackgroundJobHandler();
        $source = new InMemoryBackgroundJobSource();
        $logger = new InMemoryEventLogger();
        $result = $this->executor($handler, $source, $logger)->execute(
            $this->reserved(),
            new BackgroundWorkerIdentity(str_repeat('c', 32)),
            false,
        );

        self::assertSame(BackgroundJobExecutionOutcome::SUCCEEDED, $result->outcome());
        self::assertSame(['acknowledge'], $source->actions);
        self::assertSame(1, $handler->calls);
        self::assertInstanceOf(TestBackgroundJob::class, $handler->job);
        self::assertNotNull($handler->context);
        self::assertSame(str_repeat('a', 32), $handler->context->jobId()->value());
        self::assertSame(str_repeat('b', 32), $handler->context->correlationId()->value());
        self::assertSame(str_repeat('b', 32), $logger->records()[0]['context']['request_id']);
        self::assertSame('worker.job.succeeded', $logger->records()[1]['event']);
        self::assertStringNotContainsString(
            'QMDB_TEST_JOB_PAYLOAD_SECRET',
            json_encode($logger->records(), JSON_THROW_ON_ERROR),
        );
        self::assertStringNotContainsString(
            'opaque-reservation-token',
            json_encode($logger->records(), JSON_THROW_ON_ERROR),
        );
    }

    public function testExplicitRetryableFailureIsReleasedWithBoundedDelay(): void
    {
        $handler = new TestBackgroundJobHandler(new RetryableBackgroundJobFailure('secret detail'));
        $source = new InMemoryBackgroundJobSource();
        $logger = new InMemoryEventLogger();
        $result = $this->executor($handler, $source, $logger, 2)->execute(
            $this->reserved(attempt: 2, maximumAttempts: 3),
            new BackgroundWorkerIdentity(str_repeat('c', 32)),
            false,
        );

        self::assertSame(BackgroundJobExecutionOutcome::RETRY_SCHEDULED, $result->outcome());
        self::assertSame(['release:JOB_RETRYABLE_FAILURE'], $source->actions);
        self::assertSame('2026-08-25T09:01:00+00:00', $source->releasedAt[0]->format(DATE_ATOM));
        self::assertStringNotContainsString('secret detail', json_encode($logger->records(), JSON_THROW_ON_ERROR));
    }

    public function testAcknowledgeFailureIsClassifiedAndFailedSafely(): void
    {
        $source = new InMemoryBackgroundJobSource(failAcknowledge: true);
        $logger = new InMemoryEventLogger();
        $result = $this->executor(new TestBackgroundJobHandler(), $source, $logger)->execute(
            $this->reserved(),
            new BackgroundWorkerIdentity(str_repeat('c', 32)),
            false,
        );

        self::assertSame(BackgroundJobExecutionOutcome::FAILED, $result->outcome());
        self::assertSame(['fail:JOB_PERMANENT_FAILURE'], $source->actions);
        self::assertStringNotContainsString(
            'QMDB_ACKNOWLEDGE_FAILURE_SECRET',
            json_encode($logger->records(), JSON_THROW_ON_ERROR),
        );
    }

    #[DataProvider('terminalFailures')]
    public function testPermanentUnknownExhaustedAndInvalidReturnFailuresAreNotRetried(
        object $failureOrReturn,
        int $attempt,
        string $expectedCode,
    ): void {
        $handler = $failureOrReturn instanceof \Throwable
            ? new TestBackgroundJobHandler($failureOrReturn)
            : new TestBackgroundJobHandler(return: $failureOrReturn);
        $source = new InMemoryBackgroundJobSource();
        $result = $this->executor($handler, $source, new InMemoryEventLogger())->execute(
            $this->reserved(attempt: $attempt, maximumAttempts: 3),
            new BackgroundWorkerIdentity(str_repeat('c', 32)),
            false,
        );

        self::assertSame(BackgroundJobExecutionOutcome::FAILED, $result->outcome());
        self::assertSame(['fail:' . $expectedCode], $source->actions);
    }

    /** @return iterable<string, array{object, int, string}> */
    public static function terminalFailures(): iterable
    {
        yield 'permanent' => [new PermanentBackgroundJobFailure('secret'), 1, 'JOB_PERMANENT_FAILURE'];
        yield 'unknown throwable' => [new RuntimeException('secret'), 1, 'JOB_PERMANENT_FAILURE'];
        yield 'exhausted retryable' => [new RetryableBackgroundJobFailure('secret'), 3, 'JOB_RETRYABLE_FAILURE'];
        yield 'non-null handler result' => [(object) ['secret' => true], 1, 'JOB_PERMANENT_FAILURE'];
    }

    private function executor(
        TestBackgroundJobHandler $handler,
        InMemoryBackgroundJobSource $source,
        InMemoryEventLogger $logger,
        int $clockValues = 1,
    ): BackgroundJobExecutor {
        return new BackgroundJobExecutor(
            new BackgroundJobHandlerMap([TestBackgroundJob::class => $handler]),
            $source,
            new ConservativeBackgroundJobFailureClassifier(new ExceptionFingerprint()),
            new SequenceClock(array_fill(0, $clockValues, new DateTimeImmutable(self::NOW))),
            $logger,
        );
    }

    private function reserved(int $attempt = 1, int $maximumAttempts = 3): ReservedBackgroundJob
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
                $attempt,
                $maximumAttempts,
            ),
            new JobReservationToken('opaque-reservation-token'),
            $now->modify('+60 seconds'),
        );
    }
}
