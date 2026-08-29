<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace Qmdb\Modules\TenancyContext\Interface\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\IdentitySessions\Application\AuthenticationAttributes;
use Qmdb\Modules\TenancyContext\Application\TenantContextAttributes;
use Qmdb\Modules\TenancyContext\Application\SessionTenantContextResolver;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessAttributes;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedWorkspaceTenantContext;

final readonly class TenantContextMiddleware implements MiddlewareInterface
{
    public function __construct(private SessionTenantContextResolver $resolver)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $account = $request->getAttribute(AuthenticationAttributes::ACCOUNT);
        if (!$account instanceof AuthenticatedAccountContext) {
            return $handler->handle($request);
        }
        $privilegedWorkspace = $request->getAttribute(PrivilegedAccessAttributes::WORKSPACE_CONTEXT);
        if ($privilegedWorkspace instanceof PrivilegedWorkspaceTenantContext) {
            $request = $request
                ->withAttribute(TenantContextAttributes::VERSION, $privilegedWorkspace->tenantContextVersion())
                ->withAttribute(TenantContextAttributes::CONTEXT, $privilegedWorkspace);
            $response = $handler->handle($request);

            return $response->withHeader('X-QMDB-Tenant-Context-Version', (string) $privilegedWorkspace->tenantContextVersion());
        }
        $resolution = $this->resolver->resolve($account);
        $request = $request->withAttribute(TenantContextAttributes::VERSION, $resolution->version->value);
        if ($resolution->context !== null) {
            $request = $request->withAttribute(TenantContextAttributes::CONTEXT, $resolution->context);
        }

        $response = $handler->handle($request);
        $issuedVersion = trim($response->getHeaderLine('X-QMDB-Tenant-Context-Version'));
        if (ctype_digit($issuedVersion) && (int)$issuedVersion >= $resolution->version->value) {
            return $response;
        }

        return $response->withHeader('X-QMDB-Tenant-Context-Version', (string)$resolution->version->value);
    }
}
