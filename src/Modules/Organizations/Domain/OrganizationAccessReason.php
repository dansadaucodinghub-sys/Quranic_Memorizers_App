<?php

declare(strict_types=1);

namespace Qmdb\Modules\Organizations\Domain;

enum OrganizationAccessReason: string
{
    case ALLOWED = 'ALLOWED';
    case TENANT_CONTEXT_REQUIRED = 'TENANT_CONTEXT_REQUIRED';
    case VIEW_PERMISSION_REQUIRED = 'VIEW_PERMISSION_REQUIRED';
    case MANAGE_PERMISSION_REQUIRED = 'MANAGE_PERMISSION_REQUIRED';
    case ORGANIZATION_UNAVAILABLE = 'ORGANIZATION_UNAVAILABLE';
}
