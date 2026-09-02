<?php

declare(strict_types=1);

namespace Qmdb\Modules\OrganizationAffiliations\Application;

use Qmdb\Modules\People\Application\PersonCanonicalizationPreflight;

interface OrganizationAffiliationPersonCanonicalizationParticipant
{
    public function preflight(int $sourcePersonId, int $canonicalPersonId, int $maximumAffectedRecords): PersonCanonicalizationPreflight;

    /** Applies only within an active transaction owned by canonicalization orchestration. */
    public function apply(int $sourcePersonId, int $canonicalPersonId): int;
}
