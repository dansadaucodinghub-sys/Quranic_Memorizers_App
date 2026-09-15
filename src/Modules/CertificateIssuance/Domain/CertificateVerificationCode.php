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
        return new self(rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='));
    }

    public function value(): string { return $this->value; }
    public function hash(): string { return hash('sha256', $this->value, true); }
    public function fingerprint(): string { return substr($this->hash(), 0, 16); }
}
