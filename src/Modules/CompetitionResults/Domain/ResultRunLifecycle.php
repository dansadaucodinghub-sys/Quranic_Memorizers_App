<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionResults\Domain;

/** Result snapshots never mutate their ranking evidence after calculation. */
final readonly class ResultRunLifecycle
{
    public function assertTransition(ResultRunStatus $from, ResultRunStatus $to): void
    {
        if (!$this->allows($from, $to)) {
            throw new \DomainException(sprintf('Result-run transition %s to %s is not allowed.', $from->value, $to->value));
        }
    }

    public function allows(ResultRunStatus $from, ResultRunStatus $to): bool
    {
        return match ($from) {
            ResultRunStatus::CALCULATED => $to === ResultRunStatus::VERIFIED || $to === ResultRunStatus::VOIDED,
            ResultRunStatus::VERIFIED => $to === ResultRunStatus::PUBLISHED || $to === ResultRunStatus::VOIDED,
            ResultRunStatus::PUBLISHED => $to === ResultRunStatus::SUPERSEDED,
            ResultRunStatus::SUPERSEDED, ResultRunStatus::VOIDED => false,
        };
    }
}
