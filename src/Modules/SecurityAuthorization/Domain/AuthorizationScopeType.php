<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain;

enum AuthorizationScopeType: string
{
    case PLATFORM = 'PLATFORM';
    case WORKSPACE = 'WORKSPACE';
}
