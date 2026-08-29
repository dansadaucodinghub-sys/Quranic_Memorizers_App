<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityPrivilegedAccess\Application;

use Throwable;

/** Keeps the public readiness surface generic while failing closed on B08 drift. */
final readonly class PrivilegedAccessReadinessCheck
{
    public function __construct(private PrivilegedAccessSchemaVerifier $verifier)
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
