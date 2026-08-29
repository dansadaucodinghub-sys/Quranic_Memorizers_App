<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityPrivilegedAccess\Domain;

final readonly class PrivilegedAccessReference
{
    private function __construct(private string $value)
    {
    }

    public static function fromInput(string $value, int $maximumBytes): self
    {
        $trimmed = trim($value);
        if (
            $trimmed === '' || $maximumBytes < 1 || strlen($trimmed) > $maximumBytes
            || preg_match('/\\A[A-Za-z0-9][A-Za-z0-9._:-]*\\z/', $trimmed) !== 1
        ) {
            throw new \InvalidArgumentException('Privileged access reference is invalid.');
        }

        return new self($trimmed);
    }

    public function value(): string
    {
        return $this->value;
    }
}
