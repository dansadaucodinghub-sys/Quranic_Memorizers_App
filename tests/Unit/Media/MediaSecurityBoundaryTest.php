<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Media;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\MediaDelivery\Domain\MediaRange;
use Qmdb\Modules\MediaProcessing\Infrastructure\Security\FailClosedMediaScanner;

final class MediaSecurityBoundaryTest extends TestCase
{
    public function testUnconfiguredScannerNeverMarksContentClean(): void
    {
        self::assertFalse((new FailClosedMediaScanner())->scan('safe-looking bytes')['clean']);
    }
    public function testSingleRangesAreBoundedAndUnsatisfiableRangesFail(): void
    {
        $range=MediaRange::fromHeader('bytes=10-19',100); self::assertSame(10,$range?->start); self::assertSame(10,$range?->length());
        self::assertSame(90,MediaRange::fromHeader('bytes=-10',100)?->start);
        $this->expectException(\InvalidArgumentException::class); MediaRange::fromHeader('bytes=100-101',100);
    }
}
