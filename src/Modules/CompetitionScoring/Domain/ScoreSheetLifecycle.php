<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionScoring\Domain;

/** Score-sheet transitions; corrections create a new sheet rather than editing LOCKED data. */
final readonly class ScoreSheetLifecycle
{
    public function assertTransition(ScoreSheetStatus $from, ScoreSheetStatus $to): void
    {
        if (!$this->allows($from, $to)) {
            throw new \DomainException(sprintf('Score-sheet transition %s to %s is not allowed.', $from->value, $to->value));
        }
    }

    public function allows(ScoreSheetStatus $from, ScoreSheetStatus $to): bool
    {
        return match ($from) {
            ScoreSheetStatus::DRAFT => $to === ScoreSheetStatus::SUBMITTED || $to === ScoreSheetStatus::VOIDED,
            ScoreSheetStatus::SUBMITTED => $to === ScoreSheetStatus::DRAFT || $to === ScoreSheetStatus::LOCKED || $to === ScoreSheetStatus::VOIDED,
            ScoreSheetStatus::LOCKED => $to === ScoreSheetStatus::SUPERSEDED || $to === ScoreSheetStatus::VOIDED,
            ScoreSheetStatus::SUPERSEDED, ScoreSheetStatus::VOIDED => false,
        };
    }
}
