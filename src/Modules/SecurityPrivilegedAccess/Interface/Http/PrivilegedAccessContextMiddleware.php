<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityPrivilegedAccess\Interface\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\IdentitySessions\Application\AuthenticationAttributes;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessAttributes;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessContextResolver;
use Qmdb\Modules\TenancyContext\Domain\Repository\SessionTenantContextRepository;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Time\Clock;

/** Resolves only server-side activation state; it neither issues cookies nor trusts request parameters. */
final readonly class PrivilegedAccessContextMiddleware implements MiddlewareInterface
{
    public function __construct(
        private PrivilegedAccessContextResolver $contexts,
        private SessionTenantContextRepository $tenantContexts,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $account = $request->getAttribute(AuthenticationAttributes::ACCOUNT);
        if (!$account instanceof AuthenticatedAccountContext) {
            return $handler->handle($request);
        }
        $resolved = $this->contexts->resolve($account);
        if ($resolved === null) {
            return $handler->handle($request);
        }
        // A normal selection must never be combined with exceptional permissions. Fail closed on corruption.
        $state = $this->tenantContexts->state($account);
        if ($state->hasStoredSelection) {
            $this->transactions->transactional(function () use ($account): void {
                $locked = $this->tenantContexts->state($account, true);
                if ($locked->hasStoredSelection) {
                    $this->tenantContexts->clear($account, $locked->version, $this->clock->now());
                    $this->contexts->invalidateConflictingTenantContext($account, $this->clock->now());
                }
            });

            return $handler->handle($request);
        }
        $request = $request->withAttribute(PrivilegedAccessAttributes::CONTEXT, $resolved['context']);
        if ($resolved['workspace'] !== null) {
            $request = $request->withAttribute(PrivilegedAccessAttributes::WORKSPACE_CONTEXT, $resolved['workspace']);
        }

        return $handler->handle($request);
    }
}
