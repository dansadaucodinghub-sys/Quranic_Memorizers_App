<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Worker;

use Qmdb\Shared\Background\Job\BackgroundJobExecutionOutcome;
use Qmdb\Shared\Background\Job\BackgroundJobExecutor;
use Qmdb\Shared\Background\Source\BackgroundJobSource;
use Qmdb\Shared\Configuration\Logging\LogLevel;
use Qmdb\Shared\Database\Transaction\Sleeper;
use Qmdb\Shared\Observability\Correlation\CorrelationId;
use Qmdb\Shared\Observability\Correlation\CorrelationIdGenerator;
use Qmdb\Shared\Observability\Logging\EventLogger;
use Qmdb\Shared\Observability\Logging\LogEventName;
use Qmdb\Shared\Time\Clock;
use Qmdb\Shared\Time\MonotonicClock;
use Throwable;

final readonly class BackgroundWorker
{
    public function __construct(
        private BackgroundJobSource $source,
        private BackgroundJobExecutor $executor,
        private BackgroundWorkerIdentityGenerator $workerIdGenerator,
        private CorrelationIdGenerator $correlationIdGenerator,
        private WorkerSignalController $signals,
        private MemoryUsageProvider $memory,
        private Sleeper $sleeper,
        private Clock $clock,
        private MonotonicClock $monotonicClock,
        private EventLogger $logger,
    ) {
    }

    public function run(BackgroundWorkerOptions $options): BackgroundWorkerResult
    {
        $worker = $this->workerIdGenerator->generate();
        $correlation = $this->correlationIdGenerator->generate();
        $stop = new WorkerStopController();
        $processed = 0;
        $failed = 0;
        $started = $this->monotonicClock->nanoseconds();
        $this->signals->register($stop);
        $this->logExecution('worker.execution.started', LogLevel::INFO, $worker, $correlation, []);

        try {
            while (!$stop->isRequested()) {
                $this->requestBoundedStop($stop, $options, $started, $processed);
                if ($stop->isRequested()) {
                    break;
                }
                try {
                    $reservation = $this->source->reserve($worker, $this->clock->now());
                } catch (Throwable) {
                    $stop->request(BackgroundWorkerStopReason::SOURCE_FAILURE);
                    $this->logExecution('worker.source.failed', LogLevel::ERROR, $worker, $correlation, []);
                    break;
                }
                if ($reservation === null) {
                    if ($options->runOnce()) {
                        $stop->request(BackgroundWorkerStopReason::NO_WORK_ONCE);
                        break;
                    }
                    $this->sleeper->sleepMilliseconds(max(1, $options->idleSleepMilliseconds()));
                    continue;
                }
                try {
                    $result = $this->executor->execute($reservation, $worker, $stop->isRequested());
                    ++$processed;
                    if ($result->outcome() === BackgroundJobExecutionOutcome::FAILED) {
                        ++$failed;
                    }
                } catch (Throwable) {
                    ++$failed;
                    $stop->request(BackgroundWorkerStopReason::EXECUTION_FAILURE);
                }
            }
        } finally {
            $this->signals->restore();
        }

        $reason = $stop->reason() ?? BackgroundWorkerStopReason::MAX_RUNTIME;
        $this->logExecution('worker.execution.stopping', LogLevel::INFO, $worker, $correlation, [
            'stop_reason' => $reason->value,
        ]);
        $this->logExecution('worker.execution.completed', LogLevel::INFO, $worker, $correlation, [
            'stop_reason' => $reason->value,
            'processed_count' => $processed,
            'failed_count' => $failed,
        ]);

        return new BackgroundWorkerResult($worker, $correlation, $reason, $processed, $failed);
    }

    private function requestBoundedStop(
        WorkerStopController $stop,
        BackgroundWorkerOptions $options,
        int $started,
        int $processed,
    ): void {
        if ($processed >= $options->maximumJobs()) {
            $stop->request(BackgroundWorkerStopReason::MAX_JOBS);
        } elseif (
            $this->monotonicClock->nanoseconds() - $started
            >= $options->maximumRuntimeSeconds() * 1_000_000_000
        ) {
            $stop->request(BackgroundWorkerStopReason::MAX_RUNTIME);
        } elseif ($this->memory->bytes() >= $options->maximumMemoryBytes()) {
            $stop->request(BackgroundWorkerStopReason::MAX_MEMORY);
        }
    }

    /** @param array<string, int|string> $context */
    private function logExecution(
        string $event,
        LogLevel $level,
        BackgroundWorkerIdentity $worker,
        CorrelationId $correlation,
        array $context,
    ): void {
        try {
            $this->logger->log($level, new LogEventName($event), [
                'request_id' => $correlation->value(),
                'worker_id' => $worker->value(),
                ...$context,
            ]);
        } catch (Throwable) {
        }
    }
}
