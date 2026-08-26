<?php

declare(strict_types=1);

namespace Qmdb\Shared\Observability\Error;

use Qmdb\Shared\Configuration\Logging\LogLevel;
use Qmdb\Shared\Observability\Correlation\CorrelationId;
use Qmdb\Shared\Observability\Logging\EventLogger;
use Qmdb\Shared\Observability\Logging\LogEventName;
use Qmdb\Shared\Observability\Logging\LogContextSanitizer;
use Qmdb\Shared\Observability\Logging\SensitiveValueRedactor;
use Throwable;

final readonly class StructuredThrowableReporter implements ThrowableReporter
{
    public function __construct(
        private EventLogger $eventLogger,
        private ExceptionFingerprint $fingerprint,
        private SensitiveValueRedactor $redactor,
        private LogContextSanitizer $sanitizer,
    ) {
    }

    public function report(Throwable $throwable, CorrelationId $correlationId, array $safeContext = []): void
    {
        $providedContext = $throwable instanceof SafeLogContextProvider
            ? $throwable->safeLogContext()
            : [];
        $errorCode = $throwable instanceof SafeLogContextProvider
            ? $throwable->safeErrorCode()
            : null;
        $context = [...$safeContext, ...$providedContext];

        if ($errorCode !== null && preg_match('/\A[A-Z][A-Z0-9_]{1,63}\z/D', $errorCode) === 1) {
            $context['safe_error_code'] = $errorCode;
        }

        $context['request_id'] = $correlationId->value();
        $context['exception_class'] = $throwable::class;
        $context['exception_fingerprint'] = $this->fingerprint->forThrowable($throwable);

        $this->eventLogger->log(
            LogLevel::ERROR,
            new LogEventName('application.exception'),
            $this->sanitizer->sanitize($this->redactor->redact($context)),
        );
    }
}
