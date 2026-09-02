<?php

declare(strict_types=1);

namespace Qmdb\Modules\OrganizationAffiliations\Domain;

interface OrganizationAffiliationCodeGenerator
{
    public function generate(): string;
}
