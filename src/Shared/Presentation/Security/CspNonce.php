<?php

declare(strict_types=1);

namespace Qmdb\Shared\Presentation\Security;

use InvalidArgumentException;

final readonly class CspNonce
{
    public function __construct(private string $value)
    {
        if (strlen($value) < 22 || preg_match('/^[A-Za-z0-9_-]+$/D', $value) !== 1) {
            throw new InvalidArgumentException('CSP nonce is invalid.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    /** @return array{type: string} */
    public function __debugInfo(): array
    {
        return ['type' => 'csp-nonce-redacted'];
    }
}
