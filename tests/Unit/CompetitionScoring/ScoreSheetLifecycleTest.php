<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\CompetitionScoring;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\CompetitionScoring\Domain\ScoreSheetLifecycle;
use Qmdb\Modules\CompetitionScoring\Domain\ScoreSheetStatus;

final class ScoreSheetLifecycleTest extends TestCase
{
    public function testLockedSheetsCanOnlyBeSupersededOrVoided(): void
    {
        $lifecycle = new ScoreSheetLifecycle();

        self::assertTrue($lifecycle->allows(ScoreSheetStatus::LOCKED, ScoreSheetStatus::SUPERSEDED));
        self::assertTrue($lifecycle->allows(ScoreSheetStatus::LOCKED, ScoreSheetStatus::VOIDED));
        self::assertFalse($lifecycle->allows(ScoreSheetStatus::LOCKED, ScoreSheetStatus::DRAFT));
        self::assertFalse($lifecycle->allows(ScoreSheetStatus::SUPERSEDED, ScoreSheetStatus::LOCKED));
    }
}
