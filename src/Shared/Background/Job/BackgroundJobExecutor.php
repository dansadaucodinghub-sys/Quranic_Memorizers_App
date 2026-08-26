<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Job;

use DateInterval;
use Qmdb\Shared\Background\Source\BackgroundJobSource;
use Qmdb\Shared\Background\Worker\BackgroundWorkerIdentity;
use Qmdb\Shared\Configuration\Logging\LogLevel;
use Qmdb\Shared\Observability\Logging\EventLogger;
use Qmdb\Shared\Observability\Logging\LogEventName;
use Qmdb\Shared\Time\Clock;
use Throwable;
use UnexpectedValueException;

final readonly class BackgroundJobExecutor
{
    public function __construct(
        private BackgroundJobHandlerMap $handlers,
        private BackgroundJobSource $source,
        private BackgroundJobFailureClassifier $failureClassifier,
        private Clock $clock,
        private EventLogger $logger,
    ) {
    }

    public function execute(
        ReservedBackgroundJob $reserved,
        BackgroundWorkerIdentity $worker,
        bool $stopRequested,
    ): BackgroundJobExecutionResult {
        $envelope = $reserved->envelope();
        $context = $this->context($envelope, $worker, $stopRequested);
        $safe = $this->safeContext($envelope, $worker);
        $this->safeLog(LogLevel::INFO, 'worker.job.started', $safe);

        try {
            $handler = $this->handlers->handlerFor($envelope->job());
            $return = $handler($envelope->job(), $context);
            if ($return !== null) {
                throw new UnexpectedValueException('Background job handler must return null.');
            }
            $this->source->acknowledge($reserved);
            $this->safeLog(LogLevel::INFO, 'worker.job.succeeded', $safe);

            return new BackgroundJobExecutionResult(BackgroundJobExecutionOutcome::SUCCEEDED);
        } catch (Throwable $throwable) {
            $failure = $this->failureClassifier->classify($throwable);
            $failureContext = [
                ...$safe,
                'failure_code' => $failure->code()->value(),
                'exception_class' => $failure->exceptionClass(),
                'exception_fingerprint' => $failure->exceptionFingerprint(),
            ];
            if ($failure->isRetryable() && $envelope->attempt() < $envelope->maximumAttempts()) {
                $this->source->release($reserved, $this->retryAt($envelope->attempt()), $failure);
                $this->safeLog(LogLevel::WARNING, 'worker.job.retry_scheduled', $failureContext);

                return new BackgroundJobExecutionResult(
                    BackgroundJobExecutionOutcome::RETRY_SCHEDULED,
                    $failure,
                );
            }
            $this->source->fail($reserved, $failure);
            $this->safeLog(LogLevel::ERROR, 'worker.job.failed', $failureContext);

            return new BackgroundJobExecutionResult(BackgroundJobExecutionOutcome::FAILED, $failure);
        }
    }

    private function context(
        BackgroundJobEnvelope $envelope,
        BackgroundWorkerIdentity $worker,
        bool $stopRequested,
    ): BackgroundJobExecutionContext {
        return new BackgroundJobExecutionContext(
            $worker,
            $envelope->id(),
            $envelope->correlationId(),
            $envelope->attempt(),
            $envelope->maximumAttempts(),
            $this->clock->now(),
            $stopRequested,
        );
    }

    private function retryAt(int $attempt): \DateTimeImmutable
    {
        $delaySeconds = min(3_600, 30 * (2 ** min(6, max(0, $attempt - 1))));

        return $this->clock->now()->add(new DateInterval('PT' . $delaySeconds . 'S'));
    }

    /** @return array<string, int|string> */
    private function safeContext(
        BackgroundJobEnvelope $envelope,
        BackgroundWorkerIdentity $worker,
    ): array {
        return [
            'request_id' => $envelope->correlationId()->value(),
            'worker_id' => $worker->value(),
            'job_id' => $envelope->id()->value(),
            'job_name' => $envelope->name()->value(),
            'attempt' => $envelope->attempt(),
        ];
    }

    /** @param array<string, mixed> $context */
    private function safeLog(LogLevel $level, string $event, array $context): void
    {
        try {
            $this->logger->log($level, new LogEventName($event), $context);
        } catch (Throwable) {
        }
    }
}
