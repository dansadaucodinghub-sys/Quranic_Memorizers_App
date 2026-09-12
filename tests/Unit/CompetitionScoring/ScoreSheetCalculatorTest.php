<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\CompetitionScoring;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\CompetitionScoring\Domain\ScoreCriterion;
use Qmdb\Modules\CompetitionScoring\Domain\ScoreSheetCalculator;

final class ScoreSheetCalculatorTest extends TestCase
{
    public function testItCalculatesCanonicalFixedPointTotalsAndChecksum(): void
    {
        $criteria = [
            new ScoreCriterion('accuracy', 0, 1_000_000, 10_000, 6_000),
            new ScoreCriterion('tajwid', 0, 1_000_000, 10_000, 4_000),
        ];
        $calculator = new ScoreSheetCalculator();
        $first = $calculator->calculate($criteria, ['tajwid' => 800_000, 'accuracy' => 900_000], [10_000]);
        $second = $calculator->calculate($criteria, ['accuracy' => 900_000, 'tajwid' => 800_000], [10_000]);

        self::assertSame(['accuracy' => 540_000, 'tajwid' => 320_000], $first->weightedEntries);
        self::assertSame(850_000, $first->totalUnits);
        self::assertSame($first->checksumSha256, $second->checksumSha256);
    }

    public function testItRejectsMissingUnexpectedAndOffStepScores(): void
    {
        $criterion = new ScoreCriterion('accuracy', 0, 100_000, 10_000, 10_000);
        $calculator = new ScoreSheetCalculator();

        $this->expectException(\InvalidArgumentException::class);
        $calculator->calculate([$criterion], ['accuracy' => 15_000, 'extra' => 0]);
    }
}
