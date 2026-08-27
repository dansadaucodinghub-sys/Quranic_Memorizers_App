<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySecurityNotifications\Application;

use Qmdb\Modules\IdentitySecurityNotifications\Configuration\SecurityNotificationConfiguration;

final readonly class SecurityNotificationRetryPolicy
{
    public function __construct(private SecurityNotificationConfiguration $configuration)
    {
    }

    public function delaySeconds(int $attempt): ?int
    {
        if ($attempt >= $this->configuration->maximumAttempts) {
            return null;
        }
        $exponent = max(0, $attempt - 1);
        $delay = $this->configuration->retryBaseSeconds * (2 ** min($exponent, 30));

        return min($delay, $this->configuration->retryMaximumSeconds);
    }
}
