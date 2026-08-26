<?php

declare(strict_types=1);

namespace Qmdb\Shared\Observability\Error;

use Qmdb\Shared\Configuration\Logging\LogLevel;
use Qmdb\Shared\Observability\Correlation\CorrelationIdGenerator;
use Qmdb\Shared\Observability\Logging\EventLogger;
use Qmdb\Shared\Observability\Logging\LogEventName;
use Throwable;

final class FatalErrorShutdownReporter
{
    /** @var list<int> */
    private const FATAL_TYPES = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

    private bool $registered = false;

    public function __construct(
        private readonly LastErrorProvider $lastErrorProvider,
        private readonly EventLogger $eventLogger,
        private readonly CorrelationIdGenerator $correlationIdGenerator,
        private readonly ExceptionFingerprint $fingerprint,
    ) {
    }

    public function register(): void
    {
        if ($this->registered) {
            return;
        }

        register_shutdown_function($this->reportLastError(...));
        $this->registered = true;
    }

    public function reportLastError(): void
    {
        $error = $this->lastErrorProvider->lastError();
        if ($error === null || !in_array($error['type'], self::FATAL_TYPES, true)) {
            return;
        }

        try {
            $this->eventLogger->log(
                LogLevel::CRITICAL,
                new LogEventName('application.fatal'),
                [
                    'request_id' => $this->correlationIdGenerator->generate()->value(),
                    'fatal_type' => $error['type'],
                    'exception_fingerprint' => $this->fingerprint->forLocation(
                        'fatal:' . $error['type'],
                        $error['file'],
                        $error['line'],
                    ),
                ],
            );
        } catch (Throwable) {
            // The logger owns its terminal fallback; shutdown reporting must never recurse.
        }
    }
}
