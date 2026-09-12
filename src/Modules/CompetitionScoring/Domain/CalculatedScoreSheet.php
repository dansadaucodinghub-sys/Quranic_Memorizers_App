<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionScoring\Domain;

/** @phpstan-type WeightedEntries array<string,int> */
final readonly class CalculatedScoreSheet
{
    /** @param WeightedEntries $weightedEntries */
    public function __construct(
        public array $weightedEntries,
        public int $penaltyUnits,
        public int $totalUnits,
        public string $checksumSha256,
    ) {
    }
}
