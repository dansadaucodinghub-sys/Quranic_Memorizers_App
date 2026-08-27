<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Application\Readiness;

use Qmdb\Modules\IdentityMultiFactor\Application\SecureRecoveryCodeGenerator;
use Qmdb\Modules\IdentityMultiFactor\Application\TotpVerifier;
use Qmdb\Modules\IdentityMultiFactor\Configuration\IdentityMultiFactorConfiguration;
use Qmdb\Modules\IdentityMultiFactor\Domain\TotpSecret;
use Qmdb\Modules\IdentityMultiFactor\Infrastructure\Security\SodiumTotpSecretEncryptor;
use Qmdb\Shared\Schema\Health\SchemaHealthCheck;
use Qmdb\Shared\Security\Secrets\SecretName;
use Qmdb\Shared\Security\Secrets\SecretsProvider;
use Throwable;

final readonly class IdentityMultiFactorReadinessCheck
{
    public function __construct(
        private IdentityMultiFactorConfiguration $configuration,
        private TotpVerifier $totp,
        private SecureRecoveryCodeGenerator $recoveryCodes,
        private SecretsProvider $secrets,
        private SchemaHealthCheck $schema,
    ) {
    }

    public function isReady(): bool
    {
        try {
            if (
                !extension_loaded('sodium')
                || $this->configuration->userVerification !== 'required'
                || $this->configuration->attestation !== 'none'
                || $this->configuration->allowedOrigins === []
                || !$this->schema->check()->isReady()
            ) {
                return false;
            }
            $encodedKey = $this->secrets->get(SecretName::fromString('AUTH_MFA_ENCRYPTION_KEY'))->reveal();
            $key = base64_decode($encodedKey, true);
            if (!is_string($key)) {
                return false;
            }
            $encryptor = new SodiumTotpSecretEncryptor($key, $this->configuration->encryptionKeyVersion);
            $secret = TotpSecret::generate();
            $encrypted = $encryptor->encrypt($secret, 'readiness-account', 'readiness-authenticator');
            if (
                $encryptor->decrypt(
                    $encrypted,
                    'readiness-account',
                    'readiness-authenticator',
                )->revealForTotp() !== $secret->revealForTotp()
            ) {
                return false;
            }
            if (!str_starts_with($this->totp->provisioningUri($secret, 'readiness'), 'otpauth://totp/')) {
                return false;
            }

            return count($this->recoveryCodes->generateSet()) === $this->configuration->recoveryCodeCount;
        } catch (Throwable) {
            return false;
        }
    }
}
