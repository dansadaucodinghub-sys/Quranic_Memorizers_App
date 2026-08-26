<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Scheduler;

use InvalidArgumentException;
use Qmdb\Shared\Identifier\RuntimeIdentifierGenerator;
use Stringable;

final readonly class SchedulerExecutionId implements Stringable
{
    public function __construct(private string $value)
    {
        if (preg_match('/\A[a-f0-9]{32}\z/D', $value) !== 1) {
            throw new InvalidArgumentException('Scheduler execution ID is invalid.');
        }
    }

    public static function generate(RuntimeIdentifierGenerator $generator): self
    {
        return new self($generator->generate()->value());
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
