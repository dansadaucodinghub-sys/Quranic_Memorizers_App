<?php

declare(strict_types=1);

namespace Qmdb\Modules\Identity\Domain\Value;

use InvalidArgumentException;

final readonly class PhoneNumber
{
    private function __construct(private string $e164)
    {
    }

    public static function fromInput(string $value): self
    {
        $value = trim($value);
        if (preg_match('/\A\+[1-9][0-9]{7,14}\z/', $value) !== 1) {
            throw new InvalidArgumentException('Phone number must be canonical E.164.');
        }

        return new self($value);
    }

    public function normalized(): string
    {
        return $this->e164;
    }

    public function __debugInfo(): array
    {
        return ['value' => '[REDACTED]'];
    }
}
