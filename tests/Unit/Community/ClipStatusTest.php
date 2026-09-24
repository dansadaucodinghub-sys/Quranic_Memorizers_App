<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Community;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\Community\Domain\ClipStatus;

final class ClipStatusTest extends TestCase
{
    public function testOnlyReviewCanLeadToFirstPublication(): void
    {
        self::assertTrue(ClipStatus::DRAFT->permits(ClipStatus::REVIEW_PENDING));
        self::assertFalse(ClipStatus::DRAFT->permits(ClipStatus::PUBLISHED));
        self::assertTrue(ClipStatus::REVIEW_PENDING->permits(ClipStatus::PUBLISHED));
    }

    public function testRemovalRequiresGovernedRestorationPath(): void
    {
        self::assertFalse(ClipStatus::REMOVED->permits(ClipStatus::PUBLISHED));
        self::assertTrue(ClipStatus::REMOVED->permits(ClipStatus::HIDDEN));
        self::assertFalse(ClipStatus::ARCHIVED->permits(ClipStatus::DRAFT));
        self::assertFalse(ClipStatus::SUPERSEDED->permits(ClipStatus::PUBLISHED));
    }

    public function testInvalidTransitionFailsClosed(): void
    {
        $this->expectException(\DomainException::class);
        ClipStatus::DRAFT->assertTransition(ClipStatus::PUBLISHED);
    }
}
