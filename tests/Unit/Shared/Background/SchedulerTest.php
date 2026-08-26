<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Background;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Background\Scheduler\FixedIntervalSchedule;
use Qmdb\Shared\Background\Scheduler\ScheduledExecutionSlot;
use Qmdb\Shared\Background\Scheduler\ScheduledTask;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskClaimDisposition;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskExecutionOutcome;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskId;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskMap;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskRunRecord;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskRunStatus;
use Qmdb\Shared\Background\Scheduler\Scheduler;
use Qmdb\Shared\Background\Scheduler\SchedulerExecutionId;
use Qmdb\Shared\Observability\Error\ExceptionFingerprint;
use Qmdb\Tests\Support\Background\InMemoryScheduledTaskRunRepository;
use Qmdb\Tests\Support\Background\SequenceClock;
use Qmdb\Tests\Support\Background\SequenceRuntimeIdentifierGenerator;
use Qmdb\Tests\Support\Background\TestScheduledTaskHandler;
use Qmdb\Tests\Support\Observability\FakeMonotonicClock;
use Qmdb\Tests\Support\Observability\InMemoryEventLogger;
use Qmdb\Tests\Support\Observability\SequenceCorrelationIdGenerator;
use InvalidArgumentException;
use RuntimeException;

final class SchedulerTest extends TestCase
{
    private const NOW = '2026-08-25T09:00:07+00:00';

    #[DataProvider('fixedIntervals')]
    public function testFixedIntervalsProduceDeterministicUtcSlots(
        int $interval,
        int $offset,
        string $expectedCurrent,
        string $expectedNext,
    ): void {
        $schedule = new FixedIntervalSchedule($interval, $offset);
        $now = new DateTimeImmutable(self::NOW);

        self::assertSame($expectedCurrent, $schedule->currentDueTime($now)->format(DATE_ATOM));
        self::assertSame($expectedNext, $schedule->nextTime($now)->format(DATE_ATOM));
    }

    /** @return iterable<string, array{int, int, string, string}> */
    public static function fixedIntervals(): iterable
    {
        yield 'minute epoch' => [60, 0, '2026-08-25T09:00:00+00:00', '2026-08-25T09:01:00+00:00'];
        yield 'minute offset' => [60, 15, '2026-08-25T08:59:15+00:00', '2026-08-25T09:00:15+00:00'];
    }

    #[DataProvider('invalidIntervals')]
    public function testInvalidIntervalsAndOffsetsAreRejected(int $interval, int $offset): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FixedIntervalSchedule($interval, $offset);
    }

    /** @return iterable<string, array{int, int}> */
    public static function invalidIntervals(): iterable
    {
        yield 'zero' => [0, 0];
        yield 'negative' => [-1, 0];
        yield 'offset equal' => [60, 60];
        yield 'offset above' => [60, 61];
        yield 'negative offset' => [60, -1];
    }

    public function testScheduleNormalizesLocalTimezoneAndDropsMicrosecondsFromSlot(): void
    {
        $schedule = new FixedIntervalSchedule(60);
        $local = new DateTimeImmutable('2026-08-25T10:00:07.999999+01:00');

        self::assertSame(
            '2026-08-25T09:00:00.000000+00:00',
            $schedule->currentDueTime($local)->format('Y-m-d\TH:i:s.uP'),
        );
    }

    public function testEmptyProductionStyleMapIsADeterministicNoOp(): void
    {
        $scheduler = new Scheduler(
            new ScheduledTaskMap([]),
            new InMemoryScheduledTaskRunRepository(),
            new SequenceRuntimeIdentifierGenerator([]),
            new SequenceCorrelationIdGenerator([str_repeat('3', 32)]),
            new ExceptionFingerprint(),
            new SequenceClock([new DateTimeImmutable(self::NOW)]),
            new FakeMonotonicClock([]),
            new InMemoryEventLogger(),
        );

        $result = $scheduler->run(new DateTimeImmutable(self::NOW));
        self::assertSame(
            [0, 0, 0, 0, 0],
            [$result->due, $result->claimed, $result->succeeded, $result->failed, $result->skipped],
        );
    }

    public function testClaimedTaskRunsExactlyOnceAndThenSkipsSucceededSlot(): void
    {
        $repository = new InMemoryScheduledTaskRunRepository();
        $handler = new TestScheduledTaskHandler();
        $scheduler = $this->scheduler($repository, $handler, [0, 2_000_000, 3_000_000]);

        $first = $scheduler->run(new DateTimeImmutable(self::NOW));
        $second = $scheduler->run(new DateTimeImmutable(self::NOW));

        self::assertSame(
            [1, 1, 1, 0, 0],
            [$first->due, $first->claimed, $first->succeeded, $first->failed, $first->skipped],
        );
        self::assertSame(ScheduledTaskExecutionOutcome::SUCCEEDED, $first->results[0]->outcome);
        self::assertSame(
            [1, 0, 0, 0, 1],
            [$second->due, $second->claimed, $second->succeeded, $second->failed, $second->skipped],
        );
        self::assertSame(ScheduledTaskClaimDisposition::SKIPPED_SUCCEEDED, $second->results[0]->claimDisposition);
        self::assertSame(1, $handler->calls);
    }

    public function testHandlerFailureIsPersistedWithSafeCode(): void
    {
        $repository = new InMemoryScheduledTaskRunRepository();
        $handler = new TestScheduledTaskHandler(new RuntimeException('QMDB_SCHEDULE_SECRET'));
        $logger = new InMemoryEventLogger();
        $result = $this->scheduler($repository, $handler, [0, 1_000_000], $logger)
            ->run(new DateTimeImmutable(self::NOW));

        self::assertSame(1, $result->failed);
        self::assertSame(['claimed', 'running', 'failed:SCHEDULED_TASK_FAILED'], $repository->actions);
        self::assertStringNotContainsString(
            'QMDB_SCHEDULE_SECRET',
            json_encode($logger->records(), JSON_THROW_ON_ERROR),
        );
    }

    public function testLaterIndependentTaskContinuesAfterEarlierFailure(): void
    {
        $now = new DateTimeImmutable(self::NOW);
        $repository = new InMemoryScheduledTaskRunRepository();
        $failed = new TestScheduledTaskHandler(new RuntimeException('first task failed'));
        $succeeded = new TestScheduledTaskHandler();
        $scheduler = new Scheduler(
            new ScheduledTaskMap([
                $this->task('beta.task', $succeeded),
                $this->task('alpha.task', $failed),
            ]),
            $repository,
            new SequenceRuntimeIdentifierGenerator([str_repeat('1', 32), str_repeat('2', 32)]),
            new SequenceCorrelationIdGenerator([str_repeat('3', 32)]),
            new ExceptionFingerprint(),
            new SequenceClock(array_fill(0, 4, $now)),
            new FakeMonotonicClock([0, 1_000_000, 2_000_000, 3_000_000]),
            new InMemoryEventLogger(),
        );

        $result = $scheduler->run($now);

        self::assertSame([2, 2, 1, 1, 0], [
            $result->due,
            $result->claimed,
            $result->succeeded,
            $result->failed,
            $result->skipped,
        ]);
        self::assertSame(1, $failed->calls);
        self::assertSame(1, $succeeded->calls);
        self::assertFalse($result->isSuccessful());
        self::assertSame('+00:00', $succeeded->context?->startedAt()->format('P'));
    }

    public function testActiveLeaseSkipsAndExpiredLeaseIsReclaimed(): void
    {
        $now = new DateTimeImmutable(self::NOW);
        $taskId = new ScheduledTaskId('test.scheduled.task');
        $slot = new ScheduledExecutionSlot($taskId, (new FixedIntervalSchedule(60))->currentDueTime($now));
        $active = new InMemoryScheduledTaskRunRepository();
        $active->seed(new ScheduledTaskRunRecord(
            $slot,
            new SchedulerExecutionId(str_repeat('8', 32)),
            ScheduledTaskRunStatus::RUNNING,
            1,
            $now->modify('+1 minute'),
            2,
            null,
        ));
        $skipped = $this->scheduler($active, new TestScheduledTaskHandler(), [0])->run($now);

        self::assertSame(ScheduledTaskClaimDisposition::SKIPPED_ACTIVE, $skipped->results[0]->claimDisposition);

        $expired = new InMemoryScheduledTaskRunRepository();
        $expired->seed(new ScheduledTaskRunRecord(
            $slot,
            new SchedulerExecutionId(str_repeat('8', 32)),
            ScheduledTaskRunStatus::RUNNING,
            1,
            $now->modify('-1 second'),
            2,
            null,
        ));
        $reclaimed = $this->scheduler($expired, new TestScheduledTaskHandler(), [0, 1_000_000])->run($now);

        self::assertSame(ScheduledTaskClaimDisposition::RECLAIMED, $reclaimed->results[0]->claimDisposition);
        self::assertSame(['reclaimed', 'running', 'succeeded'], $expired->actions);
    }

    public function testRepositoryRejectsAStaleOwner(): void
    {
        $repository = new InMemoryScheduledTaskRunRepository();
        $now = new DateTimeImmutable(self::NOW);
        $slot = new ScheduledExecutionSlot(new ScheduledTaskId('test.scheduled.task'), $now);
        $first = $repository->claim($slot, new SchedulerExecutionId(str_repeat('1', 32)), $now, 1);
        $repository->claim($slot, new SchedulerExecutionId(str_repeat('2', 32)), $now->modify('+2 seconds'), 1);

        $this->expectException(RuntimeException::class);
        $repository->markRunning($first, $now);
    }

    /** @param list<int> $monotonicValues */
    private function scheduler(
        InMemoryScheduledTaskRunRepository $repository,
        TestScheduledTaskHandler $handler,
        array $monotonicValues,
        ?InMemoryEventLogger $logger = null,
    ): Scheduler {
        $now = new DateTimeImmutable(self::NOW);

        return new Scheduler(
            new ScheduledTaskMap([
                new ScheduledTask(
                    new ScheduledTaskId('test.scheduled.task'),
                    'A deterministic scheduled test task.',
                    new FixedIntervalSchedule(60),
                    $handler,
                    30,
                    'test.module',
                ),
            ]),
            $repository,
            new SequenceRuntimeIdentifierGenerator([
                str_repeat('1', 32),
                str_repeat('2', 32),
            ]),
            new SequenceCorrelationIdGenerator([
                str_repeat('3', 32),
                str_repeat('4', 32),
            ]),
            new ExceptionFingerprint(),
            new SequenceClock(array_fill(0, 8, $now)),
            new FakeMonotonicClock($monotonicValues),
            $logger ?? new InMemoryEventLogger(),
        );
    }

    private function task(string $id, TestScheduledTaskHandler $handler): ScheduledTask
    {
        return new ScheduledTask(
            new ScheduledTaskId($id),
            'An independent deterministic scheduled test task.',
            new FixedIntervalSchedule(60),
            $handler,
            30,
            'test.module',
        );
    }
}
