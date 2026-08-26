<?php

declare(strict_types=1);

namespace Qmdb\Modules\Identity\Domain\Value;

use InvalidArgumentException;

final readonly class EmailAddress
{
    private function __construct(private string $normalized)
    {
    }

    public static function fromInput(string $value): self
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 254 || !str_contains($value, '@')) {
            throw new InvalidArgumentException('Email address is invalid.');
        }
        if (preg_match('/\A[\x20-\x7e]+\z/', $value) !== 1 || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Email address is invalid.');
        }
        [$local, $domain] = explode('@', $value, 2);
        $normalized = strtolower($local) . '@' . strtolower($domain);

        return new self($normalized);
    }

    public function normalized(): string
    {
        return $this->normalized;
    }

    public function __debugInfo(): array
    {
        return ['value' => '[REDACTED]'];
    }
}
