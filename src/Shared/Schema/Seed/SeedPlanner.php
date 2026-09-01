<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Seed;

final readonly class SeedPlanner
{
    public function __construct(private SeedChecksum $checksum)
    {
    }

    /** @param array<string, SeedRecord> $records */
    public function plan(SeedRegistry $registry, array $records): SeedPlan
    {
        $pending = [];
        $blocked = false;
        $drifted = [];
        foreach ($records as $id => $record) {
            if ($registry->find(new SeedId($id)) === null || $record->status === SeedStatus::DRIFTED) {
                $blocked = true;
            }
        }
        foreach ($registry->ordered() as $seed) {
            $record = $records[$seed->id()->value()] ?? null;
            // Seeds are executed as one database transaction. A recorded failed seed
            // therefore has no committed steps and may be corrected before retry.
            if ($record !== null && !hash_equals($record->checksum, $this->checksum->binary($seed)) && $record->status !== SeedStatus::FAILED) {
                $blocked = true;
                $drifted[$seed->id()->value()] = true;
                continue;
            }
            if ($record?->status === SeedStatus::APPLIED) {
                continue;
            }
            foreach ($seed->dependencies() as $dependency) {
                if (isset($drifted[$dependency->value()])) {
                    $blocked = true;
                    continue 2;
                }
            }
            $pending[] = $seed;
        }

        return new SeedPlan($pending, $blocked);
    }
}
