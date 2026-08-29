<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain;

enum AuthorizationDecisionSource: string
{
    case ROLE_ASSIGNMENT = 'ROLE_ASSIGNMENT';
    case TEMPORARY_PRIVILEGE = 'TEMPORARY_PRIVILEGE';
    case SUPPORT_ACCESS = 'SUPPORT_ACCESS';
    case BREAK_GLASS = 'BREAK_GLASS';
}
