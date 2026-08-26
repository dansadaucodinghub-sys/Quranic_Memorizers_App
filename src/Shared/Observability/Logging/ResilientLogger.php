<?php

declare(strict_types=1);

namespace Qmdb\Shared\Observability\Logging;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Qmdb\Shared\Observability\Error\InternalErrorChannel;
use Stringable;
use Throwable;

final class ResilientLogger extends AbstractLogger
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly SensitiveValueRedactor $redactor,
        private readonly LogContextSanitizer $sanitizer,
        private readonly InternalErrorChannel $fallback,
    ) {
    }

    /** @param mixed $level
     *  @param array<string, mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $safeMessage = is_string($message) ? $message : '[stringable:' . $message::class . ']';

        try {
            $this->logger->log(
                $level,
                $safeMessage,
                $this->sanitizer->sanitize($this->redactor->redact($context)),
            );
        } catch (Throwable $throwable) {
            $event = preg_match('/\A[a-z][a-z0-9]*(?:\.[a-z][a-z0-9]*)+\z/D', $safeMessage) === 1
                ? $safeMessage
                : 'unstructured';
            $this->fallback->write(sprintf(
                'qmdb logging failure [event=%s] [type=%s]',
                $event,
                get_debug_type($throwable),
            ));
        }
    }
}
