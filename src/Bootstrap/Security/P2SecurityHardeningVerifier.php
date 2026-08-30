<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Security;

use Qmdb\Modules\SecurityAudit\Application\SecurityAuditControlVerifier;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationCatalogVerifier;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessSchemaVerifier;
use Qmdb\Modules\TenancyContext\Application\TenantContextSchemaVerifier;
use Qmdb\Modules\TenancyContext\Application\TenantRepositorySecurityVerifier;
use Qmdb\Shared\Http\Routing\RouteCollection;
use Qmdb\Shared\Http\Routing\Security\RouteSecurityVerifier;

/** Bounded, read-only composition of P2 structural security verifiers. */
final readonly class P2SecurityHardeningVerifier
{
    public function __construct(
        private AuthorizationCatalogVerifier $authorization,
        private TenantContextSchemaVerifier $tenantContext,
        private TenantRepositorySecurityVerifier $tenantRepositories,
        private PrivilegedAccessSchemaVerifier $privilegedAccess,
        private SecurityAuditControlVerifier $auditControls,
        private RouteSecurityVerifier $routes,
        private RouteCollection $routeCollection,
    ) {
    }

    public function verify(): P2SecurityHardeningVerificationReport
    {
        $authorization = $this->authorization->verify();
        $tenantContext = $this->tenantContext->verify();
        $tenantRepositories = $this->tenantRepositories->verify();
        $privilegedAccess = $this->privilegedAccess->verify();
        $auditControls = $this->auditControls->verifyControls();
        $routes = $this->routes->verify($this->routeCollection);
        $components = [
            'authorization_catalog' => $authorization->isValid(),
            'tenant_context' => $tenantContext->isValid(),
            'tenant_repositories' => $tenantRepositories->isValid(),
            'privileged_access' => $privilegedAccess->isValid(),
            'audit_controls' => $auditControls->isValid(),
            'route_security' => $routes->isValid(),
        ];
        $errors = [];
        foreach ($components as $name => $valid) {
            if (!$valid) {
                $errors[] = 'P2_SECURITY_COMPONENT_INVALID:' . $name;
            }
        }

        return new P2SecurityHardeningVerificationReport($components, $errors);
    }
}
