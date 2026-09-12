<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionResults\Domain;

/** Appeal state is append-only in persistence; a transition is represented by a new event. */
final readonly class AppealLifecycle
{
    public function assertTransition(AppealStatus $from, AppealStatus $to): void
    {
        if (!$this->allows($from, $to)) {
            throw new \DomainException(sprintf('Appeal transition %s to %s is not allowed.', $from->value, $to->value));
        }
    }

    public function allows(AppealStatus $from, AppealStatus $to): bool
    {
        return match ($from) {
            AppealStatus::SUBMITTED => $to === AppealStatus::UNDER_REVIEW || $to === AppealStatus::WITHDRAWN,
            AppealStatus::UNDER_REVIEW => $to === AppealStatus::UPHELD || $to === AppealStatus::DISMISSED || $to === AppealStatus::WITHDRAWN,
            AppealStatus::UPHELD, AppealStatus::DISMISSED, AppealStatus::WITHDRAWN => false,
        };
    }
}
