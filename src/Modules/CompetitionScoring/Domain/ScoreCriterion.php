<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionScoring\Domain;

/** Immutable rubric criterion using fixed-point integer units only. */
final readonly class ScoreCriterion
{
    public function __construct(
        public string $code,
        public int $minimumUnits,
        public int $maximumUnits,
        public int $stepUnits,
        public int $weightBasisPoints,
    ) {
        if (preg_match('/\A[a-z][a-z0-9_]{0,63}\z/', $code) !== 1) {
            throw new \InvalidArgumentException('Criterion code is invalid.');
        }
        if ($maximumUnits < $minimumUnits || $stepUnits < 1 || $weightBasisPoints < 1 || $weightBasisPoints > 10_000) {
            throw new \InvalidArgumentException('Criterion configuration is invalid.');
        }
    }

    public function accepts(int $units): bool
    {
        return $units >= $this->minimumUnits
            && $units <= $this->maximumUnits
            && (($units - $this->minimumUnits) % $this->stepUnits) === 0;
    }
}
