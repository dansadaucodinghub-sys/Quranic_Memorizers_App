<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Domain;

use InvalidArgumentException;

final readonly class TenantContextVersion
{
    public const int MAX_VALUE = 9_007_199_254_740_991;

    public function __construct(public int $value)
    {
        if ($value < 1 || $value > self::MAX_VALUE) {
            throw new InvalidArgumentException('Tenant context version must be a positive browser-safe integer.');
        }
    }
}
