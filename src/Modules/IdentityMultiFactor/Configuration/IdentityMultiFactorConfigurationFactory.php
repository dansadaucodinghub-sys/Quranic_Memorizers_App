<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Configuration;

use InvalidArgumentException;
use Qmdb\Shared\Configuration\ApplicationConfiguration;
use Qmdb\Shared\Configuration\EnvironmentVariables;

final readonly class IdentityMultiFactorConfigurationFactory
{
    public function create(
        EnvironmentVariables $variables,
        ApplicationConfiguration $application,
    ): IdentityMultiFactorConfiguration {
        $rpId = $variables->optionalString('AUTH_WEBAUTHN_RP_ID') ?? 'localhost';
        $origins = $this->origins(
            $variables->optionalString('AUTH_WEBAUTHN_ALLOWED_ORIGINS') ?? 'http://localhost:8080',
            $application->isProductionLike(),
        );
        $this->assertRelyingPartyId($rpId, $application->isProductionLike());
        $verification = $variables->optionalString('AUTH_WEBAUTHN_USER_VERIFICATION') ?? 'required';
        $attestation = $variables->optionalString('AUTH_WEBAUTHN_ATTESTATION') ?? 'none';
        if ($verification !== 'required' || $attestation !== 'none') {
            throw new InvalidArgumentException('WebAuthn verification and attestation policy is invalid.');
        }

        return new IdentityMultiFactorConfiguration(
            $application->isProductionLike(),
            $this->positive($variables, 'AUTH_TRANSACTION_TTL_SECONDS', 300),
            $this->positive($variables, 'AUTH_TRANSACTION_MAX_ATTEMPTS', 5),
            $this->positive($variables, 'AUTH_STEP_UP_GRANT_TTL_SECONDS', 300),
            $this->positive($variables, 'AUTH_STEP_UP_MAX_ATTEMPTS', 5),
            $this->positive($variables, 'AUTH_MFA_ENCRYPTION_KEY_VERSION', 1),
            $variables->optionalString('AUTH_TOTP_ISSUER') ?? 'Quran Memorizer DB',
            $this->exact($variables, 'AUTH_TOTP_PERIOD_SECONDS', 30),
            $this->exact($variables, 'AUTH_TOTP_DIGITS', 6),
            $this->nonNegative($variables, 'AUTH_TOTP_ALLOWED_DRIFT_STEPS', 1),
            $this->positive($variables, 'AUTH_TOTP_ENROLLMENT_TTL_SECONDS', 600),
            $this->bounded($variables, 'AUTH_RECOVERY_CODE_COUNT', 10, 1, 50),
            $this->bounded($variables, 'AUTH_RECOVERY_CODE_BYTES', 16, 12, 64),
            $rpId,
            $variables->optionalString('AUTH_WEBAUTHN_RP_NAME') ?? 'Quran Memorizer DB',
            $origins,
            $this->positive($variables, 'AUTH_WEBAUTHN_CHALLENGE_TTL_SECONDS', 300),
            $this->bounded($variables, 'AUTH_WEBAUTHN_MAX_RESPONSE_BYTES', 65536, 4096, 1048576),
            $verification,
            $attestation,
            $this->boolean($variables, 'AUTH_WEBAUTHN_PASSWORDLESS_ENABLED', true),
            $this->positive($variables, 'AUTH_MFA_WINDOW_SECONDS', 900),
            $this->positive($variables, 'AUTH_MFA_MAX_ATTEMPTS', 10),
            $this->positive($variables, 'AUTH_PASSKEY_WINDOW_SECONDS', 900),
            $this->positive($variables, 'AUTH_PASSKEY_MAX_ATTEMPTS', 20),
        );
    }

    private function assertRelyingPartyId(string $rpId, bool $productionLike): void
    {
        $validHostname = preg_match(
            '/\A(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*\z/',
            $rpId,
        ) === 1;
        if (
            $rpId === '' || str_contains($rpId, '://') || str_contains($rpId, '/')
            || str_contains($rpId, ':') || str_contains($rpId, '*')
            || !$validHostname
            || ($productionLike && $rpId === 'localhost')
        ) {
            throw new InvalidArgumentException('AUTH_WEBAUTHN_RP_ID is invalid.');
        }
    }

    /** @return list<string> */
    private function origins(string $raw, bool $productionLike): array
    {
        $values = array_values(array_unique(array_filter(array_map('trim', explode(',', $raw)))));
        if ($values === []) {
            throw new InvalidArgumentException('AUTH_WEBAUTHN_ALLOWED_ORIGINS must not be empty.');
        }
        foreach ($values as $origin) {
            $parts = parse_url($origin);
            $scheme = is_array($parts) && isset($parts['scheme']) ? strtolower($parts['scheme']) : '';
            $host = is_array($parts) && isset($parts['host']) ? strtolower($parts['host']) : '';
            $localHttp = !$productionLike
                && $scheme === 'http'
                && in_array($host, ['localhost', '127.0.0.1', '::1'], true);
            if (
                !is_array($parts) || !isset($parts['scheme'], $parts['host'])
                || isset($parts['path']) && $parts['path'] !== ''
                || isset($parts['query']) || isset($parts['fragment']) || isset($parts['user'])
                || str_contains($origin, '*')
                || $scheme !== 'https' && !$localHttp
            ) {
                throw new InvalidArgumentException('AUTH_WEBAUTHN_ALLOWED_ORIGINS contains an invalid origin.');
            }
        }

        return $values;
    }

    private function positive(EnvironmentVariables $variables, string $name, int $default): int
    {
        return $this->bounded($variables, $name, $default, 1, PHP_INT_MAX);
    }

    private function nonNegative(EnvironmentVariables $variables, string $name, int $default): int
    {
        return $this->bounded($variables, $name, $default, 0, PHP_INT_MAX);
    }

    private function exact(EnvironmentVariables $variables, string $name, int $expected): int
    {
        $value = $this->positive($variables, $name, $expected);
        if ($value !== $expected) {
            throw new InvalidArgumentException($name . ' must equal ' . $expected . '.');
        }

        return $value;
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
        if (preg_match('/\A(?:0|[1-9][0-9]*)\z/', $raw) !== 1) {
            throw new InvalidArgumentException($name . ' must be an integer.');
        }
        $value = (int)$raw;
        if ($value < $minimum || $value > $maximum) {
            throw new InvalidArgumentException($name . ' is outside its allowed range.');
        }

        return $value;
    }

    private function boolean(EnvironmentVariables $variables, string $name, bool $default): bool
    {
        $raw = $variables->optionalString($name);
        if ($raw === null) {
            return $default;
        }

        return match (strtolower($raw)) {
            'true', '1' => true,
            'false', '0' => false,
            default => throw new InvalidArgumentException($name . ' must be a boolean.'),
        };
    }
}
