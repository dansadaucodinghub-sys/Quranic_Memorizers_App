<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\CompetitionJudging;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\CompetitionJudging\Domain\JudgeAssignmentStatus;
use Qmdb\Modules\CompetitionScoring\Domain\ScoreSheetStatus;
use Qmdb\Modules\CompetitionResults\Domain\ResultRunStatus;

final class JudgeAssignmentStatusTest extends TestCase
{
    public function testP6LifecycleStatesAreClosedEnumerations(): void
    {
        self::assertSame('ACCEPTED', JudgeAssignmentStatus::ACCEPTED->value);
        self::assertSame('LOCKED', ScoreSheetStatus::LOCKED->value);
        self::assertSame('PUBLISHED', ResultRunStatus::PUBLISHED->value);
    }
}
