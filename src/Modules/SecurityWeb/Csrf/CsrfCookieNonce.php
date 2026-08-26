<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityWeb\Csrf;

use InvalidArgumentException;

final readonly class CsrfCookieNonce
{
    public function __construct(private string $value)
    {
        if (preg_match('/\A[A-Za-z0-9_-]{43}\z/', $value) !== 1) {
            throw new InvalidArgumentException('CSRF cookie nonce is invalid.');
        }
    }

    public static function generate(): self
    {
        return new self(rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='));
    }

    public function value(): string
    {
        return $this->value;
    }

    /** @return array{value: string} */
    public function __debugInfo(): array
    {
        return ['value' => '[REDACTED]'];
    }
}
