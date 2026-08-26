<?php

declare(strict_types=1);

namespace Qmdb\Shared\Time;

use DateTimeImmutable;
use DateTimeZone;

final readonly class UtcDateTime
{
    private function __construct()
    {
    }

    public static function normalize(DateTimeImmutable $value): DateTimeImmutable
    {
        return $value->setTimezone(new DateTimeZone('UTC'));
    }
}
