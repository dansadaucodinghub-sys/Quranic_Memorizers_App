<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Application;

final readonly class AuthenticationAttributes
{
    public const ACCOUNT = 'qmdb.authenticated_account';

    private function __construct()
    {
    }
}
