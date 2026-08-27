<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Security\Fingerprint;

use InvalidArgumentException;
use SensitiveParameter;

final readonly class HmacIdentityFingerprintGenerator implements IdentityFingerprintGenerator
{
    private const array DOMAINS = [
        'registration-email', 'registration-peer', 'verification-email', 'verification-peer',
        'verification-challenge', 'password-email', 'password-peer', 'idempotency-registration',
        'idempotency-verification-resend',
        'recovery-request-email', 'recovery-request-peer', 'recovery-attempt', 'recovery-attempt-peer',
        'idempotency-recovery-request', 'idempotency-recovery-reset',
    ];

    public function __construct(#[SensitiveParameter] private string $key)
    {
        if (strlen($key) < 32) {
            throw new InvalidArgumentException('Identity HMAC key is invalid.');
        }
    }

    public function generate(string $domain, string $value): IdentityFingerprint
    {
        if (!in_array($domain, self::DOMAINS, true)) {
            throw new InvalidArgumentException('Identity fingerprint domain is invalid.');
        }

        return new IdentityFingerprint(hash_hmac('sha256', "QMDB\0" . $domain . "\0" . $value, $this->key, true));
    }

    /** @return array{key: string} */
    public function __debugInfo(): array
    {
        return ['key' => '[REDACTED]'];
    }
}
