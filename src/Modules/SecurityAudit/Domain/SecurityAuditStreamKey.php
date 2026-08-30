<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Domain;

use InvalidArgumentException;

/**
 * Deterministic, non-secret identity key used to locate exactly one audit stream.
 */
final readonly class SecurityAuditStreamKey
{
    public function __construct(private string $value)
    {
        if (strlen($value) !== 32) {
            throw new InvalidArgumentException('Security audit stream key must be a SHA-256 digest.');
        }
    }

    public function binary(): string
    {
        return $this->value;
    }

    public function hex(): string
    {
        return bin2hex($this->value);
    }
}
