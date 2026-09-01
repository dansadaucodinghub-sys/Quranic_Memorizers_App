<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Domain;

interface PersonRegistryCodeGenerator
{
    public function generate(): PersonRegistryCode;
}
