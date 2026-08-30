<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Application;

use Throwable;

/**
 * Keeps the public readiness response generic while rejecting an unavailable audit key
 * or missing append-only database controls. Full historical chain verification remains
 * a bounded operator and release action, rather than a health-probe hot path.
 */
final readonly class SecurityAuditReadinessCheck
{
    public function __construct(private SecurityAuditControlVerifier $controls)
    {
    }

    public function isReady(): bool
    {
        try {
            return $this->controls->verifyControls()->isValid();
        } catch (Throwable) {
            return false;
        }
    }
}
