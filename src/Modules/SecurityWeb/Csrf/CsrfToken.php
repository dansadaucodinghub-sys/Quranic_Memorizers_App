<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityWeb\Csrf;

use InvalidArgumentException;

final readonly class CsrfToken
{
    public function __construct(private string $value)
    {
        if (strlen($value) > 160 || preg_match('/\Av1\.[0-9]{1,12}\.[A-Za-z0-9_-]{43}\z/', $value) !== 1) {
            throw new InvalidArgumentException('CSRF token is invalid.');
        }
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
