<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Application;

use InvalidArgumentException;

final readonly class AuthenticationCookieInstruction
{
    public function __construct(private string $header)
    {
        if ($header === '' || preg_match('/[\r\n\0]/', $header) === 1) {
            throw new InvalidArgumentException('Authentication cookie instruction is invalid.');
        }
    }

    public function revealForResponse(): string
    {
        return $this->header;
    }

    /** @return array{value: string} */
    public function __debugInfo(): array
    {
        return ['value' => '[REDACTED]'];
    }
}
