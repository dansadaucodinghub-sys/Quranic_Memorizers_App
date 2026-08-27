<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySecurityNotifications\Configuration;

use InvalidArgumentException;
use Qmdb\Shared\Configuration\EnvironmentVariables;

final readonly class SecurityNotificationConfigurationFactory
{
    public function create(EnvironmentVariables $variables): SecurityNotificationConfiguration
    {
        return new SecurityNotificationConfiguration(
            $this->nonNegative($variables, 'AUTH_SECURITY_NOTIFICATION_BATCH_SIZE', 25),
            $this->nonNegative($variables, 'AUTH_SECURITY_NOTIFICATION_MAX_ATTEMPTS', 5),
            $this->nonNegative($variables, 'AUTH_SECURITY_NOTIFICATION_LEASE_SECONDS', 120),
            $this->nonNegative($variables, 'AUTH_SECURITY_NOTIFICATION_RETRY_BASE_SECONDS', 60),
            $this->nonNegative($variables, 'AUTH_SECURITY_NOTIFICATION_RETRY_MAX_SECONDS', 3600),
        );
    }

    private function nonNegative(EnvironmentVariables $variables, string $name, int $default): int
    {
        $raw = $variables->optionalString($name);
        if ($raw === null) {
            return $default;
        }
        if (preg_match('/\A(?:0|[1-9][0-9]*)\z/', $raw) !== 1) {
            throw new InvalidArgumentException($name . ' must be a non-negative integer.');
        }

        return (int)$raw;
    }
}
