<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Domain;

use SensitiveParameter;

final readonly class NormalizedRecoveryCode
{
    public function __construct(#[SensitiveParameter] private string $value)
    {
        if (preg_match('/\A[0-9A-HJKMNP-TV-Z]{20,128}\z/', $value) !== 1) {
            throw new \InvalidArgumentException('Recovery code is invalid.');
        }
    }

    public static function fromInput(string $input): self
    {
        $value = strtoupper(str_replace(['-', ' ', "\t", "\r", "\n"], '', trim($input)));
        $value = strtr($value, ['O' => '0', 'I' => '1', 'L' => '1']);

        return new self($value);
    }

    public function hash(string $hmacKey): RecoveryCodeHash
    {
        return new RecoveryCodeHash(hash_hmac('sha256', "qmdb:recovery-code:v1\0" . $this->value, $hmacKey, true));
    }

    /** @return array{code: string} */
    public function __debugInfo(): array
    {
        return ['code' => '[REDACTED]'];
    }
}
