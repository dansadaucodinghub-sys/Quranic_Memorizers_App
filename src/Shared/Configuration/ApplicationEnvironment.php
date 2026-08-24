<?php

declare(strict_types=1);

namespace Qmdb\Shared\Configuration;

use InvalidArgumentException;

enum ApplicationEnvironment: string
{
    case LOCAL = 'local';
    case TEST = 'test';
    case STAGING = 'staging';
    case PRODUCTION = 'production';

    public static function parse(string $value): self
    {
        return self::tryFrom(trim($value))
            ?? throw new InvalidArgumentException('Unsupported application environment.');
    }

    public function isProductionLike(): bool
    {
        return $this === self::STAGING || $this === self::PRODUCTION;
    }

    public function allowsLocalEnvironmentFile(): bool
    {
        return $this === self::LOCAL || $this === self::TEST;
    }

    public function toSafeString(): string
    {
        return $this->value;
    }
}
