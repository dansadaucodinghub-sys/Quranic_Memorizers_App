<?php

declare(strict_types=1);

namespace Qmdb\Shared\Observability\Error;

use Closure;
use Throwable;

final readonly class InternalErrorChannel
{
    /** @var Closure(string): void */
    private Closure $writer;

    /** @param (callable(string): void)|null $writer */
    public function __construct(?callable $writer = null)
    {
        $this->writer = Closure::fromCallable($writer ?? static function (string $message): void {
            error_log($message);
        });
    }

    public function write(string $message): void
    {
        $safeMessage = preg_replace('/[\r\n\0]+/', ' ', $message) ?? 'qmdb internal failure';

        try {
            ($this->writer)(substr($safeMessage, 0, 512));
        } catch (Throwable) {
            // A fallback channel is terminal and must never recurse.
        }
    }
}
