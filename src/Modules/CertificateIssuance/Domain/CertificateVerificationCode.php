<?php

declare(strict_types=1);

namespace Qmdb\Modules\CertificateIssuance\Domain;

/** One-time in-memory representation.  Persistence uses only hash and fingerprint. */
final readonly class CertificateVerificationCode
{
    private function __construct(private string $value)
    {
    }

    public static function generate(): self
    {
        $value = rtrim(strtr(base64_encode(random_bytes(CertificatePublicIdentifierPolicy::VERIFICATION_CODE_RANDOM_BYTES)), '+/', '-_'), '=');
        if (!CertificatePublicIdentifierPolicy::isVerificationCode($value)) {
            throw new \RuntimeException('Generated certificate verification code is invalid.');
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }
    public function hash(): string
    {
        return hash('sha256', $this->value, true);
    }
    public function fingerprint(): string
    {
        return substr($this->hash(), 0, 16);
    }
}
