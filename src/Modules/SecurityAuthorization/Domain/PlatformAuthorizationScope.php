<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain;

final readonly class PlatformAuthorizationScope implements AuthorizationScope
{
    public function type(): AuthorizationScopeType
    {
        return AuthorizationScopeType::PLATFORM;
    }
}
