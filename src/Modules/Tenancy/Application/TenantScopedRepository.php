<?php

declare(strict_types=1);

namespace Qmdb\Modules\Tenancy\Application;

/** Marker for repositories whose data operations require an explicit tenant boundary. */
interface TenantScopedRepository
{
}
