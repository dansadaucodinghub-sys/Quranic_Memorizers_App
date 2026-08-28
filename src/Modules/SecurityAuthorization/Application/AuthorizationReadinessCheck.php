<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Application;

use Throwable;

final readonly class AuthorizationReadinessCheck
{
    public function __construct(private AuthorizationCatalogVerifier $verifier)
    {
    }

    public function isReady(): bool
    {
        try {
            return $this->verifier->verify()->isValid();
        } catch (Throwable) {
            return false;
        }
    }
}
