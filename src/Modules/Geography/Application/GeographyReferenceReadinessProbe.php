<?php

declare(strict_types=1);

namespace Qmdb\Modules\Geography\Application;

interface GeographyReferenceReadinessProbe
{
    public function isActiveProjectionValid(): bool;
}
