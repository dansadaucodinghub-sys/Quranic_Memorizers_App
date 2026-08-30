<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Configuration;

use InvalidArgumentException;
use Qmdb\Shared\Configuration\ApplicationConfiguration;
use Qmdb\Shared\Configuration\EnvironmentVariables;

final readonly class SecurityAuditConfigurationFactory
{
    public function create(EnvironmentVariables $variables, ApplicationConfiguration $application): SecurityAuditConfiguration
    {
        return new SecurityAuditConfiguration(
            $application->isProductionLike(),
            $this->bounded($variables, 'AUTH_SECURITY_AUDIT_METADATA_MAX_BYTES', 4096, 256, 4096),
            $this->bounded($variables, 'AUTH_SECURITY_AUDIT_VERIFY_BATCH_SIZE', 1000, 1, 10000),
            $this->bounded($variables, 'AUTH_SECURITY_AUDIT_CHECKPOINT_INTERVAL_SECONDS', 3600, 60, 86400),
            $this->bounded($variables, 'AUTH_SECURITY_AUDIT_CHECKPOINT_MAX_STREAMS', 10000, 1, 100000),
            $this->bounded($variables, 'AUTH_SECURITY_AUDIT_HMAC_KEY_VERSION', 1, 1, 65535),
        );
    }

    private function bounded(EnvironmentVariables $variables, string $name, int $default, int $minimum, int $maximum): int
    {
        $raw = $variables->optionalString($name);
        if ($raw === null) {
            return $default;
        }
        if (preg_match('/\\A[1-9][0-9]*\\z/', $raw) !== 1) {
            throw new InvalidArgumentException($name . ' must be a positive integer.');
        }
        $value = (int) $raw;
        if ($value < $minimum || $value > $maximum) {
            throw new InvalidArgumentException($name . ' is outside its approved range.');
        }

        return $value;
    }
}
