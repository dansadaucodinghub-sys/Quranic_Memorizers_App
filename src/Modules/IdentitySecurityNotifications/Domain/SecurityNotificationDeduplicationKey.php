<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySecurityNotifications\Domain;

use InvalidArgumentException;

final readonly class SecurityNotificationDeduplicationKey
{
    public function __construct(private string $value)
    {
        if (strlen($value) !== 32) {
            throw new InvalidArgumentException('Security notification deduplication key must be 32 bytes.');
        }
    }

    public function toBinary(): string
    {
        return $this->value;
    }
}
