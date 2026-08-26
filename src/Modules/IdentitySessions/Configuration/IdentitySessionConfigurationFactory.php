<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Configuration;

use InvalidArgumentException;
use Qmdb\Shared\Configuration\ApplicationConfiguration;
use Qmdb\Shared\Configuration\EnvironmentVariables;

final readonly class IdentitySessionConfigurationFactory
{
    public function create(
        EnvironmentVariables $variables,
        ApplicationConfiguration $application,
    ): IdentitySessionConfiguration {
        return new IdentitySessionConfiguration(
            $application->isProductionLike(),
            $this->nonNegative($variables, 'AUTH_SESSION_IDLE_TTL_SECONDS', 1800),
            $this->nonNegative($variables, 'AUTH_SESSION_ABSOLUTE_TTL_SECONDS', 43200),
            $this->nonNegative($variables, 'AUTH_SESSION_ROTATION_INTERVAL_SECONDS', 900),
            $this->nonNegative($variables, 'AUTH_SESSION_PREVIOUS_TOKEN_GRACE_SECONDS', 30),
            $this->nonNegative($variables, 'AUTH_SESSION_TOUCH_INTERVAL_SECONDS', 60),
            $this->nonNegative($variables, 'AUTH_SESSION_MAX_ACTIVE_PER_ACCOUNT', 10),
            $this->nonNegative($variables, 'AUTH_DEVICE_COOKIE_TTL_SECONDS', 31536000),
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
