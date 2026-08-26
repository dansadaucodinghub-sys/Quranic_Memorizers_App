<?php

declare(strict_types=1);

namespace Qmdb\Shared\Console\Observability;

use Qmdb\Shared\Configuration\Logging\LogLevel;
use Qmdb\Shared\Observability\Correlation\CorrelationIdGenerator;
use Qmdb\Shared\Observability\Error\ExceptionFingerprint;
use Qmdb\Shared\Observability\Error\ThrowableReporter;
use Qmdb\Shared\Observability\Logging\EventLogger;
use Qmdb\Shared\Observability\Logging\LogEventName;
use Qmdb\Shared\Time\MonotonicClock;
use Throwable;

final readonly class ConsoleExecutionObserver
{
    public function __construct(
        private CorrelationIdGenerator $correlationIdGenerator,
        private EventLogger $eventLogger,
        private MonotonicClock $clock,
        private ThrowableReporter $throwableReporter,
        private ExceptionFingerprint $fingerprint,
    ) {
    }

    public function start(string $command): ConsoleExecutionContext
    {
        $safeCommand = preg_match('/\A[a-z][a-z0-9]*(?::[a-z][a-z0-9]*)*\z/D', $command) === 1
            && strlen($command) <= 64
            ? $command
            : 'invalid';
        $context = new ConsoleExecutionContext(
            $this->correlationIdGenerator->generate(),
            $safeCommand,
            $this->clock->nanoseconds(),
        );
        $this->safeLog(LogLevel::INFO, 'console.command.started', [
            'request_id' => $context->correlationId()->value(),
            'command' => $context->command(),
        ]);

        return $context;
    }

    public function complete(ConsoleExecutionContext $context, int $exitCode): void
    {
        $this->safeLog($exitCode === 0 ? LogLevel::INFO : LogLevel::NOTICE, 'console.command.completed', [
            'request_id' => $context->correlationId()->value(),
            'command' => $context->command(),
            'exit_code' => $exitCode,
            'duration_ms' => $this->durationMilliseconds($context),
        ]);
    }

    public function fail(ConsoleExecutionContext $context, Throwable $throwable, int $exitCode): void
    {
        $this->throwableReporter->report($throwable, $context->correlationId(), [
            'execution' => 'console',
            'command' => $context->command(),
        ]);
        $this->safeLog(LogLevel::ERROR, 'console.command.failed', [
            'request_id' => $context->correlationId()->value(),
            'command' => $context->command(),
            'exit_code' => $exitCode,
            'duration_ms' => $this->durationMilliseconds($context),
            'exception_class' => $throwable::class,
            'exception_fingerprint' => $this->fingerprint->forThrowable($throwable),
        ]);
    }

    /** @param array<string, mixed> $context */
    private function safeLog(LogLevel $level, string $event, array $context): void
    {
        try {
            $this->eventLogger->log($level, new LogEventName($event), $context);
        } catch (Throwable) {
            // Operational logging cannot become command authority.
        }
    }

    private function durationMilliseconds(ConsoleExecutionContext $context): float
    {
        return round(
            max(0, $this->clock->nanoseconds() - $context->startedAtNanoseconds()) / 1_000_000,
            3,
        );
    }
}
