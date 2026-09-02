<?php

declare(strict_types=1);

namespace Qmdb\Modules\Organizations\Domain;

interface OrganizationRegistryCodeGenerator
{
    public function organization(): string;
    public function unit(): string;
}
