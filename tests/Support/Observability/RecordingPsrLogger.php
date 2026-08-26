<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Observability;

use Psr\Log\AbstractLogger;
use Stringable;

final class RecordingPsrLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string, context: array<string, mixed>}> */
    private array $records = [];

    /** @param mixed $level
     *  @param array<string, mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    /** @return list<array{level: mixed, message: string, context: array<string, mixed>}> */
    public function records(): array
    {
        return $this->records;
    }
}
