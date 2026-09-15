<?php

declare(strict_types=1);

namespace Qmdb\Modules\RecordPassport\Domain;

/** Opaque revocable public-share locator; storage retains only derived material. */
final readonly class RecordPassportShareCode
{
    private function __construct(private string $value)
    {
    }

    public static function generate(): self { return new self(rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=')); }
    public function value(): string { return $this->value; }
    public function hash(): string { return hash('sha256', $this->value, true); }
    public function fingerprint(): string { return substr($this->hash(), 0, 16); }
}
