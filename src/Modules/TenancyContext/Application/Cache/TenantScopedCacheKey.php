<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Application\Cache;

final readonly class TenantScopedCacheKey
{
    public function __construct(private string $value)
    {
        if ($value === '' || strlen($value) > 250) {
            throw new \InvalidArgumentException('Tenant-scoped cache key is invalid.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
