<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Application;

interface PersonCanonicalizationParticipant
{
    public function preflight(int $sourcePersonId, int $canonicalPersonId, int $maximumAffectedRecords): PersonCanonicalizationPreflight;

    /** Applies only within the caller-owned active database transaction. */
    public function apply(int $sourcePersonId, int $canonicalPersonId, int $actorAccountId): int;
}
