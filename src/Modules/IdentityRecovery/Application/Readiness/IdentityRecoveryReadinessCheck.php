<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityRecovery\Application\Readiness;

use DateTimeImmutable;
use Qmdb\Modules\IdentityAccess\Configuration\IdentityAccessConfiguration;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\Password\PasswordHashingPolicy;
use Qmdb\Modules\IdentityAccess\Security\Password\PasswordPolicy;
use Qmdb\Modules\IdentityAccess\Security\Password\SensitivePlaintextPassword;
use Qmdb\Modules\IdentityRecovery\Configuration\IdentityRecoveryConfiguration;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfCookieNonce;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfTokenManager;
use Qmdb\Shared\Schema\Health\SchemaHealthCheck;
use Qmdb\Shared\Security\Secrets\SecretName;
use Qmdb\Shared\Security\Secrets\SecretsProvider;
use Symfony\Component\Mailer\Transport;
use Throwable;

final readonly class IdentityRecoveryReadinessCheck
{
    public function __construct(
        private IdentityRecoveryConfiguration $configuration,
        private IdentityAccessConfiguration $identityAccess,
        private PasswordHashingPolicy $hashing,
        private PasswordPolicy $passwords,
        private CsrfTokenManager $csrf,
        private IdentityFingerprintGenerator $fingerprints,
        private SecretsProvider $secrets,
        private SchemaHealthCheck $schema,
    ) {
    }

    public function isReady(): bool
    {
        try {
            if (
                !defined('PASSWORD_ARGON2ID')
                || $this->configuration->ttlSeconds < 60
                || $this->configuration->maximumAttempts < 1
                || $this->hashing->metadataVersion < 1
                || !$this->passwords->evaluate(
                    new SensitivePlaintextPassword('readiness-only-passphrase'),
                    'readiness-only-passphrase',
                )->accepted()
                || !$this->schema->check()->isReady()
            ) {
                return false;
            }
            if (parse_url($this->identityAccess->publicBaseUrl->value(), PHP_URL_HOST) === null) {
                return false;
            }
            $dsn = $this->secrets->get(SecretName::fromString('MAILER_DSN'));
            Transport::fromDsn($dsn->reveal());
            if ($this->identityAccess->productionLike && str_starts_with(strtolower($dsn->reveal()), 'null:')) {
                return false;
            }
            $nonce = CsrfCookieNonce::generate();
            $now = new DateTimeImmutable();
            $this->csrf->issue(CsrfAction::ACCOUNT_PASSWORD_RECOVERY_REQUEST, $nonce, $now);
            $this->csrf->issue(CsrfAction::ACCOUNT_PASSWORD_RECOVERY_RESET, $nonce, $now);
            $this->fingerprints->generate('recovery-request-peer', 'readiness-probe');

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
