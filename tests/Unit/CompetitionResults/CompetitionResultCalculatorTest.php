<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\CompetitionResults;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\CompetitionResults\Domain\CompetitionResultCalculator;
use Qmdb\Modules\CompetitionResults\Domain\DeterministicRanking;
use Qmdb\Modules\CompetitionResults\Domain\PanelScoreAggregator;

final class CompetitionResultCalculatorTest extends TestCase
{
    public function testItCalculatesCanonicalMeanRanksAndChecksums(): void
    {
        $calculator = new CompetitionResultCalculator(new PanelScoreAggregator(), new DeterministicRanking());
        $result = $calculator->calculate('MEAN', [
            ['participant_public_id' => 'b', 'public_label' => 'Entry B', 'total_units' => 89_000, 'score_sheet_checksum' => str_repeat('b', 64)],
            ['participant_public_id' => 'a', 'public_label' => 'Entry A', 'total_units' => 91_000, 'score_sheet_checksum' => str_repeat('a', 64)],
            ['participant_public_id' => 'a', 'public_label' => 'Entry A', 'total_units' => 89_000, 'score_sheet_checksum' => str_repeat('c', 64)],
        ], [['basis' => 'TOTAL', 'direction' => 'DESC']]);

        self::assertSame('a', $result->rows[0]['participantPublicId']);
        self::assertSame(90_000, $result->rows[0]['totalUnits']);
        self::assertSame(1, $result->rows[0]['rank']);
        self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $result->inputChecksum);
        self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $result->resultChecksum);
    }

    public function testItRejectsIncompleteLockedInput(): void
    {
        $this->expectException(\DomainException::class);
        (new CompetitionResultCalculator(new PanelScoreAggregator(), new DeterministicRanking()))->calculate('MEAN', [
            ['participant_public_id' => 'a', 'public_label' => '', 'total_units' => 10, 'score_sheet_checksum' => 'x'],
        ], []);
    }
}
