<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityPrivilegedAccess\Domain;

final readonly class PrivilegedAccessDuration
{
    private function __construct(public int $seconds)
    {
    }

    public static function requested(int $seconds, int $maximumSeconds): self
    {
        if ($seconds < 1 || $seconds > $maximumSeconds) {
            throw new \InvalidArgumentException('Privileged access duration is outside its allowed maximum.');
        }

        return new self($seconds);
    }

    public static function approved(int $seconds, self $requested, int $maximumSeconds): self
    {
        if ($seconds < 1 || $seconds > $requested->seconds || $seconds > $maximumSeconds) {
            throw new \InvalidArgumentException('Privileged access approved duration is invalid.');
        }

        return new self($seconds);
    }

    public static function minimum(self $left, self $right): self
    {
        return new self(min($left->seconds, $right->seconds));
    }
}
