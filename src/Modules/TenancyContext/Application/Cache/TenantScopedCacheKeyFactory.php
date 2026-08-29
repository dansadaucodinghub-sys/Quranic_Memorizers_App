<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Application\Cache;

use Qmdb\Modules\TenancyContext\Domain\AccountWorkspaceTenantContext;

final readonly class TenantScopedCacheKeyFactory
{
    public function create(
        AccountWorkspaceTenantContext $context,
        string $namespace,
        int $schemaVersion,
        string $keyMaterial,
    ): TenantScopedCacheKey {
        if (
            preg_match('/\A[a-z][a-z0-9._-]{1,63}\z/', $namespace) !== 1
            || $schemaVersion < 1 || $keyMaterial === ''
        ) {
            throw new \InvalidArgumentException('Tenant-scoped cache key input is invalid.');
        }

        return new TenantScopedCacheKey(sprintf(
            'qmdb:tenant:%s:%s:v%d:%s',
            $context->workspaceId->toString(),
            $namespace,
            $schemaVersion,
            hash('sha256', $keyMaterial),
        ));
    }
}
