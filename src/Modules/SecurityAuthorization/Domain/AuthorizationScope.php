<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain;

interface AuthorizationScope
{
    public function type(): AuthorizationScopeType;
}
