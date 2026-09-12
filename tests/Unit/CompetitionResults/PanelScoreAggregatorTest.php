<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\CompetitionResults;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\CompetitionResults\Domain\PanelScoreAggregator;

final class PanelScoreAggregatorTest extends TestCase
{
    public function testItUsesIntegerAggregationForEverySupportedMethod(): void
    {
        $aggregator = new PanelScoreAggregator();
        self::assertSame(22, $aggregator->aggregate('MEAN', [10, 20, 35]));
        self::assertSame(21, $aggregator->aggregate('MEDIAN', [10, 20, 21, 50]));
        self::assertSame(20, $aggregator->aggregate('TRIMMED_MEAN', [1, 10, 30, 99]));
    }

    public function testTrimmedMeanRejectsAnInsufficientPanel(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new PanelScoreAggregator())->aggregate('TRIMMED_MEAN', [1, 2]);
    }
}
