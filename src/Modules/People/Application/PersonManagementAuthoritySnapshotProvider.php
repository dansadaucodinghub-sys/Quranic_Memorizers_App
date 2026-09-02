<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Application;

interface PersonManagementAuthoritySnapshotProvider
{
    /** @return list<array{person_id:int,account_id:int,authority_type:string,guardianship_id:?int}> */
    public function authoritiesForPerson(int $personId): array;
}
