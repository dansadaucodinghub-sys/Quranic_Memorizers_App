<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Time;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Time\Clock;
use Qmdb\Shared\Time\SystemClock;

final class SystemClockTest extends TestCase
{
    public function testClockReturnsDateTimeImmutable(): void
    {
        self::assertInstanceOf(DateTimeImmutable::class, (new SystemClock())->now());
    }

    public function testClockAlwaysReturnsUtc(): void
    {
        self::assertSame('UTC', (new SystemClock())->now()->getTimezone()->getName());
    }

    public function testDefaultPhpTimezoneDoesNotAffectClock(): void
    {
        $previousTimezone = date_default_timezone_get();

        try {
            date_default_timezone_set('Africa/Lagos');
            self::assertSame('UTC', (new SystemClock())->now()->getTimezone()->getName());
        } finally {
            date_default_timezone_set($previousTimezone);
        }
    }

    public function testConsecutiveCallsAreChronologicallyValidAndContractIsImplemented(): void
    {
        $clock = new SystemClock();
        $first = $clock->now();
        $second = $clock->now();

        self::assertInstanceOf(Clock::class, $clock);
        self::assertGreaterThanOrEqual($first, $second);
    }
}
