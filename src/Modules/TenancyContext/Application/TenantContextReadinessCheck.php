<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Application;

use Throwable;

final readonly class TenantContextReadinessCheck
{
    public function __construct(private TenantContextSchemaVerifier $verifier)
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
