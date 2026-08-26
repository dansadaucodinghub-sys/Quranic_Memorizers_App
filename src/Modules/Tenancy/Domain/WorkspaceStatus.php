<?php

declare(strict_types=1);

namespace Qmdb\Modules\Tenancy\Domain;

enum WorkspaceStatus: string
{
    case ACTIVE = 'ACTIVE';
    case SUSPENDED = 'SUSPENDED';
    case CLOSED = 'CLOSED';
}
