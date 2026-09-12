<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionJudging\Domain;

/**
 * Keeps round transitions explicit. The application layer remains responsible
 * for authorization, step-up, version checks, and the transaction boundary.
 */
final readonly class RoundLifecycle
{
    public function assertTransition(RoundStatus $from, RoundStatus $to): void
    {
        if (!$this->allows($from, $to)) {
            throw new \DomainException(sprintf('Round transition %s to %s is not allowed.', $from->value, $to->value));
        }
    }

    public function allows(RoundStatus $from, RoundStatus $to): bool
    {
        return match ($from) {
            RoundStatus::DRAFT => $to === RoundStatus::READY || $to === RoundStatus::CANCELLED,
            RoundStatus::READY => $to === RoundStatus::SCORING_OPEN || $to === RoundStatus::CANCELLED,
            RoundStatus::SCORING_OPEN => $to === RoundStatus::SCORING_CLOSED || $to === RoundStatus::CANCELLED,
            RoundStatus::SCORING_CLOSED => $to === RoundStatus::RESULTS_CALCULATED || $to === RoundStatus::CANCELLED,
            RoundStatus::RESULTS_CALCULATED => $to === RoundStatus::RESULTS_VERIFIED || $to === RoundStatus::SCORING_CLOSED,
            RoundStatus::RESULTS_VERIFIED => $to === RoundStatus::RESULTS_PUBLISHED || $to === RoundStatus::SCORING_CLOSED,
            RoundStatus::RESULTS_PUBLISHED, RoundStatus::CANCELLED => false,
        };
    }
}
