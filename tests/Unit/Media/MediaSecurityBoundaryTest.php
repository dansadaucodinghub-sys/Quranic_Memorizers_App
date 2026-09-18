<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Media;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\MediaDelivery\Domain\MediaRange;
use Qmdb\Modules\MediaCatalog\Domain\MediaLifecycle;
use Qmdb\Modules\MediaProcessing\Infrastructure\Security\FailClosedMediaScanner;
use Qmdb\Modules\MediaProcessing\Infrastructure\Security\DeterministicMediaScanner;

final class MediaSecurityBoundaryTest extends TestCase
{
    public function testUnconfiguredScannerNeverMarksContentClean(): void
    {
        self::assertFalse((new FailClosedMediaScanner())->scan('safe-looking bytes')['clean']);
    }
    public function testSingleRangesAreBoundedAndUnsatisfiableRangesFail(): void
    {
        $range = MediaRange::fromHeader('bytes=10-19', 100);
        self::assertNotNull($range);
        self::assertSame(10, $range->start);
        self::assertSame(10, $range->length());
        self::assertSame(90, MediaRange::fromHeader('bytes=-10', 100)?->start);
        $this->expectException(\InvalidArgumentException::class);
        MediaRange::fromHeader('bytes=100-101', 100);
    }
    public function testEvidenceLifecycleDoesNotPermitDirectPublicationOrDeliveryWhileHeld(): void
    {
        $lifecycle = new MediaLifecycle();
        $this->expectException(\DomainException::class);
        $lifecycle->assertTransition('STAGING', 'PUBLISHED');
    }
    public function testTestScannerIsDeterministicAndNeverClassifiesEicarAsClean(): void
    {
        $scanner = new DeterministicMediaScanner();
        self::assertTrue($scanner->scan('plain fixture bytes')['clean']);
        self::assertFalse($scanner->scan('EICAR-STANDARD-ANTIVIRUS-TEST-FILE')['clean']);
        self::assertSame('TEST_ONLY', $scanner->check()['safe_code']);
    }
    public function testDeliveryRequiresApprovedStateRightsConsentAndNoHold(): void
    {
        $lifecycle = new MediaLifecycle();
        self::assertTrue($lifecycle->isDeliverable('APPROVED', true, true, false));
        self::assertFalse($lifecycle->isDeliverable('PUBLISHED', true, true, true));
        self::assertFalse($lifecycle->isDeliverable('PROCESSING', true, true, false));
    }
}
