<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Migration;

final readonly class MigrationPlanner
{
    public function __construct(private MigrationChecksum $checksum)
    {
    }

    /** @param array<string, MigrationRecord> $records */
    public function plan(MigrationRegistry $registry, array $records): MigrationPlan
    {
        $items = [];
        $statuses = [];
        foreach ($registry->ordered() as $migration) {
            $id = $migration->id()->value();
            $record = $records[$id] ?? null;
            $statuses[$id] = $this->status($migration, $record);
        }
        foreach ($registry->ordered() as $migration) {
            $id = $migration->id()->value();
            $status = $statuses[$id];
            foreach ($migration->dependencies() as $dependency) {
                $dependencyStatus = $statuses[$dependency->value()];
                if (
                    in_array($dependencyStatus, [
                    MigrationPlanStatus::FAILED,
                    MigrationPlanStatus::DRIFTED,
                    MigrationPlanStatus::ORPHANED,
                    MigrationPlanStatus::BLOCKED_BY_DEPENDENCY,
                    ], true)
                ) {
                    $status = MigrationPlanStatus::BLOCKED_BY_DEPENDENCY;
                    break;
                }
            }
            $items[] = new MigrationPlanItem($id, $migration->description(), $status);
        }
        foreach ($records as $id => $record) {
            if ($registry->find(new MigrationId($id)) === null) {
                $items[] = new MigrationPlanItem(
                    $id,
                    'Applied migration is absent from the registry.',
                    MigrationPlanStatus::ORPHANED,
                );
            }
        }

        return new MigrationPlan($items);
    }

    private function status(Migration $migration, ?MigrationRecord $record): MigrationPlanStatus
    {
        if ($record === null) {
            return MigrationPlanStatus::PENDING;
        }
        // A failed/partial migration has not established a completed migration contract.
        // Its recorded steps remain immutable, but its unapplied steps may be corrected
        // and then resumed under the existing schema mutation lock.
        if (
            !hash_equals($record->checksum, $this->checksum->migrationBinary($migration))
            && !in_array($record->status, [MigrationStatus::PARTIAL, MigrationStatus::RUNNING], true)
        ) {
            return MigrationPlanStatus::DRIFTED;
        }
        foreach ($record->appliedStepChecksums as $stepId => $storedChecksum) {
            $match = null;
            foreach ($migration->up() as $step) {
                if ($step->id()->value() === $stepId) {
                    $match = $step;
                    break;
                }
            }
            if ($match === null || !hash_equals($storedChecksum, $this->checksum->stepBinary($match))) {
                return MigrationPlanStatus::DRIFTED;
            }
        }

        return match ($record->status) {
            MigrationStatus::APPLIED => MigrationPlanStatus::APPLIED,
            MigrationStatus::PARTIAL, MigrationStatus::RUNNING => MigrationPlanStatus::PARTIAL,
            MigrationStatus::FAILED, MigrationStatus::ROLLBACK_FAILED => MigrationPlanStatus::FAILED,
            MigrationStatus::ROLLED_BACK => MigrationPlanStatus::ROLLED_BACK,
            MigrationStatus::DRIFTED => MigrationPlanStatus::DRIFTED,
            MigrationStatus::ROLLING_BACK => MigrationPlanStatus::FAILED,
        };
    }
}
