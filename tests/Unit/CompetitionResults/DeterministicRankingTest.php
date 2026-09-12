<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\CompetitionResults;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\CompetitionResults\Domain\DeterministicRanking;

final class DeterministicRankingTest extends TestCase
{
    public function testItUsesScoreThenConfiguredTieBreakThenStablePublicIdentifier(): void
    {
        $ranked = (new DeterministicRanking())->standard([
            ['participantId' => 'p-b', 'totalUnits' => 900, 'tieBreak' => [4]],
            ['participantId' => 'p-a', 'totalUnits' => 900, 'tieBreak' => [5]],
            ['participantId' => 'p-c', 'totalUnits' => 800, 'tieBreak' => [9]],
        ]);

        self::assertSame(['p-a', 'p-b', 'p-c'], array_column($ranked, 'participantId'));
        self::assertSame([1, 2, 3], array_column($ranked, 'rank'));
    }

    public function testItUsesStandardCompetitionRanksForAnUnbrokenTie(): void
    {
        $ranked = (new DeterministicRanking())->standard([
            ['participantId' => 'p-a', 'totalUnits' => 900, 'tieBreak' => [1]],
            ['participantId' => 'p-b', 'totalUnits' => 900, 'tieBreak' => [1]],
            ['participantId' => 'p-c', 'totalUnits' => 800, 'tieBreak' => [1]],
        ]);

        self::assertSame([1, 1, 3], array_column($ranked, 'rank'));
    }

    public function testItSupportsDenseRanksWithoutChangingTieDeterminism(): void
    {
        $ranked = (new DeterministicRanking())->dense([
            ['participantId' => 'p-a', 'totalUnits' => 900, 'tieBreak' => [1]],
            ['participantId' => 'p-b', 'totalUnits' => 900, 'tieBreak' => [1]],
            ['participantId' => 'p-c', 'totalUnits' => 800, 'tieBreak' => [1]],
        ]);

        self::assertSame(['p-a', 'p-b', 'p-c'], array_column($ranked, 'participantId'));
        self::assertSame([1, 1, 2], array_column($ranked, 'rank'));
    }
}
