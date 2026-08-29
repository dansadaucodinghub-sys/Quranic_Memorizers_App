<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityPrivilegedAccess\Configuration;

use InvalidArgumentException;
use Qmdb\Shared\Configuration\EnvironmentVariables;

final readonly class PrivilegedAccessConfigurationFactory
{
    public function create(EnvironmentVariables $variables): PrivilegedAccessConfiguration
    {
        $temporary = $this->bounded($variables, 'AUTH_TEMPORARY_PRIVILEGE_MAX_TTL_SECONDS', 14400, 1, 86400);
        $support = $this->bounded($variables, 'AUTH_SUPPORT_ACCESS_MAX_TTL_SECONDS', 7200, 1, 86400);
        $breakGlass = $this->bounded($variables, 'AUTH_BREAK_GLASS_MAX_TTL_SECONDS', 900, 1, 86400);
        if ($breakGlass > $support || $support > $temporary) {
            throw new InvalidArgumentException('Privileged access duration configuration is inconsistent.');
        }

        return new PrivilegedAccessConfiguration(
            $this->bounded($variables, 'AUTH_PRIVILEGED_ACCESS_REQUEST_TTL_SECONDS', 86400, 1, 604800),
            $temporary,
            $support,
            $breakGlass,
            $this->bounded($variables, 'AUTH_PRIVILEGED_ACCESS_MAX_PERMISSIONS', 16, 1, 64),
            $this->bounded($variables, 'AUTH_PRIVILEGED_ACCESS_REVIEW_TTL_SECONDS', 86400, 1, 604800),
            $this->bounded($variables, 'AUTH_PRIVILEGED_ACCESS_JUSTIFICATION_MAX_BYTES', 2000, 64, 2000),
            $this->bounded($variables, 'AUTH_PRIVILEGED_ACCESS_REFERENCE_MAX_BYTES', 128, 1, 128),
            $this->bounded($variables, 'AUTH_PRIVILEGED_ACCESS_MAINTENANCE_BATCH_SIZE', 50, 1, 500),
            $this->bounded($variables, 'AUTH_PRIVILEGED_ACCESS_REQUEST_WINDOW_SECONDS', 900, 1, 86400),
            $this->bounded($variables, 'AUTH_PRIVILEGED_ACCESS_REQUEST_MAX_ATTEMPTS', 10, 1, 100),
            $this->bounded($variables, 'AUTH_BREAK_GLASS_WINDOW_SECONDS', 3600, 1, 86400),
            $this->bounded($variables, 'AUTH_BREAK_GLASS_MAX_ATTEMPTS', 3, 1, 20),
        );
    }

    private function bounded(
        EnvironmentVariables $variables,
        string $name,
        int $default,
        int $minimum,
        int $maximum,
    ): int {
        $raw = $variables->optionalString($name);
        if ($raw === null) {
            return $default;
        }
        if (preg_match('/\\A(?:0|[1-9][0-9]*)\\z/', $raw) !== 1) {
            throw new InvalidArgumentException($name . ' must be an integer.');
        }
        $value = (int) $raw;
        if ($value < $minimum || $value > $maximum) {
            throw new InvalidArgumentException($name . ' is outside its allowed range.');
        }

        return $value;
    }
}
