<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Configuration;

use InvalidArgumentException;
use Qmdb\Shared\Configuration\ApplicationConfiguration;
use Qmdb\Shared\Configuration\EnvironmentVariables;

final readonly class IdentityResolutionConfigurationFactory
{
    public function create(EnvironmentVariables $variables, ApplicationConfiguration $application): IdentityResolutionConfiguration
    {
        $key = trim((string) $variables->optionalString('AUTH_PROFILE_CLAIM_PAIRING_HMAC_KEY'));
        if ($key === '') {
            if ($application->isProductionLike()) {
                throw new InvalidArgumentException('AUTH_PROFILE_CLAIM_PAIRING_HMAC_KEY is required in production-like environments.');
            }
            $key = 'qmdb-non-production-profile-claim-pairing-hmac-key';
        }
        if (strlen($key) < 32) {
            throw new InvalidArgumentException('AUTH_PROFILE_CLAIM_PAIRING_HMAC_KEY must be at least 32 bytes.');
        }
        if (
            hash_equals($key, (string) $variables->optionalString('AUTH_SECURITY_AUDIT_HMAC_KEY'))
            || hash_equals($key, (string) $variables->optionalString('AUTH_MFA_ENCRYPTION_KEY'))
        ) {
            throw new InvalidArgumentException('AUTH_PROFILE_CLAIM_PAIRING_HMAC_KEY must be distinct from audit and MFA keys.');
        }

        $configuration = new IdentityResolutionConfiguration(
            $key,
            $this->integer($variables, 'AUTH_PROFILE_CLAIM_PAIRING_HMAC_KEY_VERSION', 1, 1, 255),
            $this->integer($variables, 'PERSON_PROFILE_CLAIM_PAIRING_ENTROPY_BITS', 128, 128, 256),
            $this->integer($variables, 'PERSON_PROFILE_CLAIM_PAIRING_TTL_SECONDS', 1_800, 60, 86_400),
            $this->integer($variables, 'PERSON_PROFILE_CLAIM_PAIRING_MAX_ATTEMPTS', 10, 1, 100),
            $this->integer($variables, 'PERSON_PROFILE_CLAIM_TTL_SECONDS', 604_800, 1_800, 2_592_000),
            $this->integer($variables, 'PERSON_PROFILE_CLAIM_MAX_PAGE_SIZE', 50, 1, 100),
            $this->integer($variables, 'PERSON_PROFILE_DUPLICATE_MAX_AFFECTED_AFFILIATIONS', 500, 1, 10_000),
            $this->integer($variables, 'PERSON_PROFILE_DUPLICATE_MAX_CONSENT_AUTHORITIES', 20, 1, 100),
            $this->integer($variables, 'PERSON_PROFILE_REVIEW_JUSTIFICATION_MAX_BYTES', 2_000, 1, 2_000),
            $this->integer($variables, 'PERSON_PROFILE_REVIEW_REFERENCE_MAX_BYTES', 128, 1, 128),
            $this->integer($variables, 'PERSON_PROFILE_CLAIM_MAINTENANCE_BATCH_SIZE', 100, 1, 500),
            $this->integer($variables, 'PERSON_PROFILE_CLAIM_WINDOW_SECONDS', 900, 60, 86_400),
            $this->integer($variables, 'PERSON_PROFILE_CLAIM_MAX_ATTEMPTS', 20, 1, 500),
            $this->integer($variables, 'PERSON_PROFILE_DUPLICATE_WINDOW_SECONDS', 900, 60, 86_400),
            $this->integer($variables, 'PERSON_PROFILE_DUPLICATE_MAX_ATTEMPTS', 20, 1, 500),
        );
        if ($configuration->claimTtlSeconds < $configuration->pairingTtlSeconds) {
            throw new InvalidArgumentException('PERSON_PROFILE_CLAIM_TTL_SECONDS must not be shorter than pairing TTL.');
        }

        return $configuration;
    }

    private function integer(EnvironmentVariables $variables, string $name, int $default, int $minimum, int $maximum): int
    {
        $value = $variables->optionalString($name);
        if ($value === null) {
            return $default;
        }
        if (preg_match('/\A[1-9][0-9]*\z/D', $value) !== 1 || (int) $value < $minimum || (int) $value > $maximum) {
            throw new InvalidArgumentException($name . ' is outside its approved range.');
        }

        return (int) $value;
    }
}
