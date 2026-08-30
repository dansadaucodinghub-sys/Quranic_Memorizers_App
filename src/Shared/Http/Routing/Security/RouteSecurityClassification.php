<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Routing\Security;

enum RouteSecurityClassification: string
{
    case PUBLIC = 'PUBLIC';
    case OPTIONAL_AUTHENTICATION = 'OPTIONAL_AUTHENTICATION';
    case AUTHENTICATED = 'AUTHENTICATED';
    case TENANT_REQUIRED = 'TENANT_REQUIRED';
    case BASE_ROLE_REQUIRED = 'BASE_ROLE_REQUIRED';
    case PRIVILEGED_CONTEXT_ALLOWED = 'PRIVILEGED_CONTEXT_ALLOWED';

    public function requiresAuthentication(): bool
    {
        return $this !== self::PUBLIC && $this !== self::OPTIONAL_AUTHENTICATION;
    }
}
