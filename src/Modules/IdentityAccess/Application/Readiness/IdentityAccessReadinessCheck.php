<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Application\Readiness;

use Qmdb\Modules\IdentityAccess\Configuration\IdentityAccessConfiguration;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\Password\PasswordHashingPolicy;
use Qmdb\Modules\IdentityAccess\Security\Password\PasswordPolicy;
use Qmdb\Modules\IdentityAccess\Security\Password\SensitivePlaintextPassword;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfCookieNonce;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfTokenManager;
use Qmdb\Shared\Schema\Health\SchemaHealthCheck;
use Qmdb\Shared\Security\Secrets\SecretName;
use Qmdb\Shared\Security\Secrets\SecretsProvider;
use Symfony\Component\Mailer\Transport;
use Throwable;

final readonly class IdentityAccessReadinessCheck
{
    public function __construct(
        private PasswordHashingPolicy $hashing,
        private PasswordPolicy $passwords,
        private CsrfTokenManager $csrf,
        private IdentityFingerprintGenerator $fingerprints,
        private SecretsProvider $secrets,
        private IdentityAccessConfiguration $configuration,
        private SchemaHealthCheck $schema,
    ) {
    }

    public function isReady(): bool
    {
        try {
            if (
                !defined('PASSWORD_ARGON2ID')
                || $this->hashing->metadataVersion < 1
                || !$this->passwords->evaluate(
                    new SensitivePlaintextPassword('readiness-only-passphrase'),
                    'readiness-only-passphrase',
                )->accepted()
                || !$this->schema->check()->isReady()
            ) {
                return false;
            }
            $dsn = $this->secrets->get(SecretName::fromString('MAILER_DSN'));
            Transport::fromDsn($dsn->reveal());
            if ($this->configuration->productionLike && str_starts_with(strtolower($dsn->reveal()), 'null:')) {
                return false;
            }
            $nonce = CsrfCookieNonce::generate();
            $this->csrf->issue(\Qmdb\Modules\SecurityWeb\Csrf\CsrfAction::ACCOUNT_REGISTER, $nonce, new \DateTimeImmutable());
            $this->fingerprints->generate('registration-peer', 'readiness-probe');
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
