<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionResults\Domain;

/** Integer-only panel aggregation for mean, median, and trimmed mean. */
final readonly class PanelScoreAggregator
{
    /** @param list<mixed> $scores */
    public function aggregate(string $method, array $scores): int
    {
        if ($scores === []) {
            throw new \InvalidArgumentException('Panel scores must be a non-empty list of integers.');
        }
        $integerScores = [];
        foreach ($scores as $score) {
            if (!is_int($score)) {
                throw new \InvalidArgumentException('Panel scores must be a non-empty list of integers.');
            }
            $integerScores[] = $score;
        }
        sort($integerScores, SORT_NUMERIC);

        return match ($method) {
            'MEAN' => $this->roundedMean($integerScores),
            'MEDIAN' => $this->median($integerScores),
            'TRIMMED_MEAN' => $this->trimmedMean($integerScores),
            default => throw new \InvalidArgumentException('Unsupported panel aggregation method.'),
        };
    }

    /** @param list<int> $scores */
    private function roundedMean(array $scores): int
    {
        return intdiv(array_sum($scores) + intdiv(count($scores), 2), count($scores));
    }
    /** @param list<int> $scores */
    private function median(array $scores): int
    {
        $count = count($scores);
        if (($count % 2) === 1) {
            return $scores[intdiv($count, 2)];
        }
        $left = $scores[intdiv($count, 2) - 1];
        $right = $scores[intdiv($count, 2)];
        return intdiv($left + $right + 1, 2);
    }
    /** @param list<int> $scores */
    private function trimmedMean(array $scores): int
    {
        if (count($scores) < 3) {
            throw new \InvalidArgumentException('Trimmed mean needs at least three panel scores.');
        }
        array_shift($scores);
        array_pop($scores);
        return $this->roundedMean($scores);
    }
}
