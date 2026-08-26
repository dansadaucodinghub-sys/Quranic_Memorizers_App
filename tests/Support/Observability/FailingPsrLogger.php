<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Observability;

use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;

final class FailingPsrLogger extends AbstractLogger
{
    /** @param mixed $level
     *  @param array<string, mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        throw new RuntimeException('test logger failure');
    }
}
