<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Migration;

final readonly class MigrationPlan
{
    /** @param list<MigrationPlanItem> $items */
    public function __construct(public array $items)
    {
    }

    public function isBlocked(): bool
    {
        foreach ($this->items as $item) {
            if (
                in_array($item->status, [
                MigrationPlanStatus::DRIFTED,
                MigrationPlanStatus::FAILED,
                MigrationPlanStatus::ORPHANED,
                MigrationPlanStatus::BLOCKED_BY_DEPENDENCY,
                ], true)
            ) {
                return true;
            }
        }

        return false;
    }

    /** @return list<MigrationPlanItem> */
    public function pending(): array
    {
        return array_values(array_filter(
            $this->items,
            static fn (MigrationPlanItem $item): bool => in_array(
                $item->status,
                [MigrationPlanStatus::PENDING, MigrationPlanStatus::PARTIAL, MigrationPlanStatus::ROLLED_BACK],
                true,
            ),
        ));
    }
}
