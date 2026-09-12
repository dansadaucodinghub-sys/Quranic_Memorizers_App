<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionScoring\Domain;

/**
 * Server-authoritative, deterministic calculation for one sheet. Inputs are
 * canonicalized by criterion code before checksum generation.
 */
final readonly class ScoreSheetCalculator
{
    /**
     * @param list<ScoreCriterion> $criteria
     * @param array<string, mixed> $enteredUnits
     * @param list<mixed> $penaltyUnits
     */
    public function calculate(array $criteria, array $enteredUnits, array $penaltyUnits = []): CalculatedScoreSheet
    {
        $seen = [];
        $weighted = [];
        $total = new FixedPointScore(0);
        foreach ($criteria as $criterion) {
            if (isset($seen[$criterion->code])) {
                throw new \InvalidArgumentException('Duplicate criterion code.');
            }
            $seen[$criterion->code] = true;
            $raw = $enteredUnits[$criterion->code] ?? null;
            if (!is_int($raw) || !$criterion->accepts($raw)) {
                throw new \InvalidArgumentException('Missing or invalid criterion score: ' . $criterion->code);
            }
            $value = (new FixedPointScore($raw))->weighted($criterion->weightBasisPoints);
            $weighted[$criterion->code] = $value->units;
            $total = $total->plus($value);
        }
        if (count($enteredUnits) !== count($criteria)) {
            throw new \InvalidArgumentException('Unexpected criterion score submitted.');
        }
        $penalty = 0;
        foreach ($penaltyUnits as $units) {
            if (!is_int($units) || $units < 0) {
                throw new \InvalidArgumentException('Penalty must be a non-negative integer unit value.');
            }
            $penalty += $units;
        }
        ksort($weighted, SORT_STRING);
        $checksum = hash('sha256', json_encode([
            'criteria' => $weighted,
            'penalty_units' => $penalty,
            'total_units' => $total->units - $penalty,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return new CalculatedScoreSheet($weighted, $penalty, $total->units - $penalty, $checksum);
    }
}
