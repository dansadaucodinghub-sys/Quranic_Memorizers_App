<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\IdentityAccess;

use DateTimeImmutable;
use Qmdb\Shared\Time\Clock;

final readonly class FixedIdentityClock implements Clock
{
    public function __construct(private DateTimeImmutable $now)
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
