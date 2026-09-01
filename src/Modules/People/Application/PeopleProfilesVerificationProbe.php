<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Application;

interface PeopleProfilesVerificationProbe
{
    /** @return array{person_count:int,self_linked_person_count:int,dependent_person_count:int,active_role_profile_count:int,active_guardianship_count:int,invalid_rows:int} */
    public function report(): array;
}
