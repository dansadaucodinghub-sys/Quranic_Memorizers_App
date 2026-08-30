<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccountState\Configuration;

use Qmdb\Shared\Configuration\EnvironmentVariables;

final readonly class AccountStateConfigurationFactory
{
    public function create(EnvironmentVariables $variables): AccountStateConfiguration
    {
        return new AccountStateConfiguration(
            $this->bounded($variables, 'AUTH_ACCOUNT_STATE_JUSTIFICATION_MAX_BYTES', 2000, 64, 2000),
            $this->bounded($variables, 'AUTH_ACCOUNT_STATE_REFERENCE_MAX_BYTES', 128, 1, 128),
            $this->bounded($variables, 'AUTH_ACCOUNT_STATE_WINDOW_SECONDS', 900, 1, 86400),
            $this->bounded($variables, 'AUTH_ACCOUNT_STATE_MAX_ATTEMPTS', 10, 1, 100),
        );
    }

    private function bounded(EnvironmentVariables $variables, string $name, int $default, int $minimum, int $maximum): int
    {
        $value = $variables->optionalString($name);
        if ($value === null) {
            return $default;
        }
        if (preg_match('/\\A[1-9][0-9]*\\z/', $value) !== 1 || (int) $value < $minimum || (int) $value > $maximum) {
            throw new \InvalidArgumentException($name . ' is outside its approved range.');
        }

        return (int) $value;
    }
}
