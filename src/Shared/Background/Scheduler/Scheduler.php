<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Scheduler;

use Qmdb\Shared\Configuration\Logging\LogLevel;
use Qmdb\Shared\Identifier\RuntimeIdentifierGenerator;
use Qmdb\Shared\Observability\Correlation\CorrelationId;
use Qmdb\Shared\Observability\Correlation\CorrelationIdGenerator;
use Qmdb\Shared\Observability\Error\ExceptionFingerprint;
use Qmdb\Shared\Observability\Logging\EventLogger;
use Qmdb\Shared\Observability\Logging\LogEventName;
use Qmdb\Shared\Time\Clock;
use Qmdb\Shared\Time\MonotonicClock;
use Throwable;
use UnexpectedValueException;

final readonly class Scheduler
{
    public function __construct(
        private ScheduledTaskMap $tasks,
        private ScheduledTaskRunRepository $repository,
        private RuntimeIdentifierGenerator $identifierGenerator,
        private CorrelationIdGenerator $correlationIdGenerator,
        private ExceptionFingerprint $fingerprint,
        private Clock $clock,
        private MonotonicClock $monotonicClock,
        private EventLogger $logger,
    ) {
    }

    public function run(?\DateTimeImmutable $now = null): SchedulerRunResult
    {
        $correlation = $this->correlationIdGenerator->generate();
        $now ??= $this->clock->now();
        $this->safeLog(LogLevel::INFO, 'scheduler.execution.started', $correlation, []);
        $results = [];
        $claimed = 0;
        $succeeded = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($this->tasks->tasks() as $task) {
            $slot = new ScheduledExecutionSlot($task->id(), $task->schedule()->currentDueTime($now));
            $executionId = SchedulerExecutionId::generate($this->identifierGenerator);
            try {
                $claim = $this->repository->claim($slot, $executionId, $now, $task->leaseSeconds());
                if (!$claim->isAcquired()) {
                    ++$skipped;
                    $results[] = new ScheduledTaskExecutionResult(
                        $task->id(),
                        ScheduledTaskExecutionOutcome::SKIPPED,
                        $claim->disposition(),
                    );
                    $this->taskLog('scheduler.task.skipped', LogLevel::INFO, $correlation, $slot, [
                        'claim_disposition' => $claim->disposition()->value,
                    ]);
                    continue;
                }
                ++$claimed;
                $claimEvent = $claim->disposition() === ScheduledTaskClaimDisposition::RECLAIMED
                    ? 'scheduler.task.reclaimed'
                    : 'scheduler.task.claimed';
                $this->taskLog($claimEvent, LogLevel::INFO, $correlation, $slot, [
                    'execution_id' => $claim->executionId()->value(),
                    'task_attempt' => $claim->attempt(),
                ]);
                $outcome = $this->executeTask($task, $claim, $correlation);
                $results[] = $outcome;
                if ($outcome->outcome === ScheduledTaskExecutionOutcome::SUCCEEDED) {
                    ++$succeeded;
                } else {
                    ++$failed;
                }
            } catch (Throwable $throwable) {
                ++$failed;
                $results[] = new ScheduledTaskExecutionResult(
                    $task->id(),
                    ScheduledTaskExecutionOutcome::FAILED,
                    ScheduledTaskClaimDisposition::SKIPPED_ACTIVE,
                );
                $this->taskLog('scheduler.task.failed', LogLevel::ERROR, $correlation, $slot, [
                    'failure_code' => 'SCHEDULER_OPERATION_FAILED',
                    'exception_class' => $throwable::class,
                    'exception_fingerprint' => $this->fingerprint->forThrowable($throwable),
                ]);
            }
        }

        $result = new SchedulerRunResult(
            count($results),
            $claimed,
            $succeeded,
            $failed,
            $skipped,
            $results,
        );
        $this->safeLog(LogLevel::INFO, 'scheduler.execution.completed', $correlation, [
            'due_count' => $result->due,
            'claimed_count' => $result->claimed,
            'succeeded_count' => $result->succeeded,
            'failed_count' => $result->failed,
            'skipped_count' => $result->skipped,
        ]);

        return $result;
    }

    private function executeTask(
        ScheduledTask $task,
        ScheduledTaskRunClaim $claim,
        CorrelationId $correlation,
    ): ScheduledTaskExecutionResult {
        $startedAt = $this->clock->now();
        $started = $this->monotonicClock->nanoseconds();
        $running = $this->repository->markRunning($claim, $startedAt);
        $context = new ScheduledTaskExecutionContext(
            $claim->slot(),
            $claim->executionId()->value(),
            $correlation,
            $claim->attempt(),
            $startedAt,
            $claim->leaseExpiresAt(),
        );
        $this->taskLog('scheduler.task.started', LogLevel::INFO, $correlation, $claim->slot(), [
            'execution_id' => $claim->executionId()->value(),
            'task_attempt' => $claim->attempt(),
        ]);
        try {
            $return = ($task->handler())($context);
            if ($return !== null) {
                throw new UnexpectedValueException('Scheduled task handler must return null.');
            }
            $duration = $this->durationMilliseconds($started);
            $this->repository->markSucceeded($running, $this->clock->now(), $duration);
            $this->taskLog('scheduler.task.succeeded', LogLevel::INFO, $correlation, $claim->slot(), [
                'execution_id' => $claim->executionId()->value(),
                'duration_ms' => $duration,
            ]);

            return new ScheduledTaskExecutionResult(
                $task->id(),
                ScheduledTaskExecutionOutcome::SUCCEEDED,
                $claim->disposition(),
            );
        } catch (Throwable $throwable) {
            $duration = $this->durationMilliseconds($started);
            $this->repository->markFailed(
                $running,
                $this->clock->now(),
                $duration,
                'SCHEDULED_TASK_FAILED',
            );
            $this->taskLog('scheduler.task.failed', LogLevel::ERROR, $correlation, $claim->slot(), [
                'execution_id' => $claim->executionId()->value(),
                'duration_ms' => $duration,
                'failure_code' => 'SCHEDULED_TASK_FAILED',
                'exception_class' => $throwable::class,
                'exception_fingerprint' => $this->fingerprint->forThrowable($throwable),
            ]);

            return new ScheduledTaskExecutionResult(
                $task->id(),
                ScheduledTaskExecutionOutcome::FAILED,
                $claim->disposition(),
            );
        }
    }

    private function durationMilliseconds(int $started): int
    {
        return max(0, (int) round(($this->monotonicClock->nanoseconds() - $started) / 1_000_000));
    }

    /** @param array<string, int|string> $context */
    private function taskLog(
        string $event,
        LogLevel $level,
        CorrelationId $correlation,
        ScheduledExecutionSlot $slot,
        array $context,
    ): void {
        $this->safeLog($level, $event, $correlation, [
            'task_id' => $slot->taskId()->value(),
            'scheduled_for' => $slot->scheduledFor()->format('Y-m-d\TH:i:s.uP'),
            ...$context,
        ]);
    }

    /** @param array<string, int|string> $context */
    private function safeLog(
        LogLevel $level,
        string $event,
        CorrelationId $correlation,
        array $context,
    ): void {
        try {
            $this->logger->log($level, new LogEventName($event), [
                'request_id' => $correlation->value(),
                ...$context,
            ]);
        } catch (Throwable) {
        }
    }
}
