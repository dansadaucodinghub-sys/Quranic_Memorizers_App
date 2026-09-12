<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\CompetitionScoring;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\CompetitionScoring\Domain\FixedPointScore;

final class FixedPointScoreTest extends TestCase
{
    public function testItCalculatesWithIntegersAndHalfUpBasisPointRounding(): void
    {
        $score = FixedPointScore::fromWholePoints(87);
        self::assertSame(870_000, $score->units);
        self::assertSame(522_000, $score->weighted(6_000)->units);
        self::assertSame(502_000, $score->weighted(6_000)->minus(FixedPointScore::fromWholePoints(2))->units);
    }

    public function testItRejectsInvalidWeights(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        FixedPointScore::fromWholePoints(1)->weighted(10_001);
    }
}
