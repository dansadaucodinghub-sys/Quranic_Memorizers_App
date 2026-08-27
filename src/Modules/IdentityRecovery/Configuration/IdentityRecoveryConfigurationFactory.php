<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityRecovery\Configuration;

use InvalidArgumentException;
use Qmdb\Shared\Configuration\EnvironmentVariables;

final readonly class IdentityRecoveryConfigurationFactory
{
    public function create(EnvironmentVariables $variables): IdentityRecoveryConfiguration
    {
        return new IdentityRecoveryConfiguration(
            $this->positive($variables, 'AUTH_PASSWORD_RECOVERY_TTL_SECONDS', 1800),
            $this->positive($variables, 'AUTH_PASSWORD_RECOVERY_MAX_ATTEMPTS', 5),
            $this->positive($variables, 'AUTH_PASSWORD_RECOVERY_REQUEST_WINDOW_SECONDS', 900),
            $this->positive($variables, 'AUTH_PASSWORD_RECOVERY_REQUEST_MAX_ATTEMPTS', 3),
            $this->positive($variables, 'AUTH_PASSWORD_RECOVERY_CONFIRM_WINDOW_SECONDS', 900),
            $this->positive($variables, 'AUTH_PASSWORD_RECOVERY_CONFIRM_MAX_ATTEMPTS', 10),
        );
    }

    private function positive(EnvironmentVariables $variables, string $name, int $default): int
    {
        $raw = $variables->optionalString($name);
        if ($raw === null) {
            return $default;
        }
        if (preg_match('/\A[1-9][0-9]*\z/', $raw) !== 1) {
            throw new InvalidArgumentException($name . ' must be a positive integer.');
        }

        return (int)$raw;
    }
}
