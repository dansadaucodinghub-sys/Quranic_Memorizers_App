<?php

declare(strict_types=1);

namespace Qmdb\Shared\Observability\Logging;

use Psr\Log\LoggerInterface;
use Qmdb\Shared\Configuration\Logging\LogLevel;

final readonly class PsrEventLogger implements EventLogger
{
    public function __construct(
        private LoggerInterface $logger,
        private SensitiveValueRedactor $redactor,
        private LogContextSanitizer $sanitizer,
    ) {
    }

    public function log(LogLevel $level, LogEventName $event, array $context = []): void
    {
        $this->logger->log(
            $level->value,
            $event->value(),
            $this->sanitizer->sanitize($this->redactor->redact($context)),
        );
    }
}
