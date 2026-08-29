<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityPrivilegedAccess\Domain;

use LogicException;

final readonly class PrivilegedAccessJustification
{
    private function __construct(private string $value)
    {
    }

    public static function fromInput(string $value, int $maximumBytes): self
    {
        $trimmed = trim($value);
        if (
            $trimmed === '' || $maximumBytes < 64 || strlen($trimmed) > $maximumBytes
            || !mb_check_encoding($trimmed, 'UTF-8') || str_contains($trimmed, "\0")
            || preg_match('/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F\\x7F]/u', $trimmed) === 1
            || preg_match('/<\\/?[a-z][^>]*>/iu', $trimmed) === 1
        ) {
            throw new \InvalidArgumentException('Privileged access justification is invalid.');
        }

        return new self($trimmed);
    }

    public function reveal(): string
    {
        return $this->value;
    }

    public function fingerprint(): string
    {
        return hash('sha256', $this->value);
    }

    public function jsonSerialize(): never
    {
        throw new LogicException('Privileged access justification cannot be serialized.');
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['justification' => '[redacted]'];
    }
}
