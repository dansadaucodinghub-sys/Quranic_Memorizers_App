<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionScoring\Domain;

/**
 * Integer-only score arithmetic. Values are stored in ten-thousandths of a
 * point; PHP floats are deliberately never accepted by this value object.
 */
final readonly class FixedPointScore
{
    public const int SCALE = 10_000;

    public function __construct(public int $units)
    {
    }

    public static function fromWholePoints(int $points): self
    {
        return new self($points * self::SCALE);
    }

    public function plus(self $other): self
    {
        return new self($this->units + $other->units);
    }

    /** Applies a basis-point weight with half-up rounding. */
    public function weighted(int $basisPoints): self
    {
        if ($basisPoints < 0 || $basisPoints > 10_000) {
            throw new \InvalidArgumentException('Score weight must be 0 through 10000 basis points.');
        }

        $numerator = $this->units * $basisPoints;
        $rounded = intdiv($numerator + 5_000, 10_000);

        return new self($rounded);
    }

    public function minus(self $penalty): self
    {
        return new self($this->units - $penalty->units);
    }
}
