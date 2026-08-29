<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Application;

use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\TenancyContext\Application\Exception\TenantContextRequiredException;
use Qmdb\Modules\TenancyContext\Domain\AccountWorkspaceTenantContext;
use Qmdb\Modules\TenancyContext\Domain\TenantContextVersion;

final readonly class TenantContextRequiredGuard
{
    public function __construct(private TenantContextFreshnessValidator $freshness)
    {
    }

    public function require(
        ServerRequestInterface $request,
        bool $requireFreshHeader = false,
    ): AccountWorkspaceTenantContext {
        $context = $request->getAttribute(TenantContextAttributes::CONTEXT);
        if (!$context instanceof AccountWorkspaceTenantContext) {
            throw new TenantContextRequiredException();
        }
        if ($requireFreshHeader) {
            $this->freshness->validate($context->version, $this->providedVersion($request), true);
        }

        return $context;
    }

    private function providedVersion(ServerRequestInterface $request): ?TenantContextVersion
    {
        $provided = trim($request->getHeaderLine('X-QMDB-Tenant-Context-Version'));
        if ($provided === '') {
            return null;
        }
        if (preg_match('/\A[1-9][0-9]{0,15}\z/', $provided) !== 1) {
            return null;
        }

        try {
            return new TenantContextVersion((int)$provided);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
