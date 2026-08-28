<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain;

enum AuthorizationDecisionOutcome: string
{
    case ALLOW = 'ALLOW';
    case DENY = 'DENY';
}
