<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Routing\Security;

use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationCatalogRegistry;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Shared\Http\Routing\HttpMethod;
use Qmdb\Shared\Http\Routing\Route;
use Qmdb\Shared\Http\Routing\RouteCollection;

final readonly class RouteSecurityVerifier
{
    public function __construct(private ProductionRouteSecurityPolicyCatalog $catalog)
    {
    }

    public function verify(RouteCollection $routes): RouteSecurityVerificationReport
    {
        $policies = $this->catalog->policies();
        $catalog = AuthorizationCatalogRegistry::withAuditAccountState();
        $errors = [];
        $classified = 0;
        $mutations = 0;
        $csrfProtected = 0;
        foreach ($routes as $route) {
            $policy = $policies[$route->name()] ?? null;
            if (!$policy instanceof RouteSecurityPolicy) {
                $errors[] = 'Unclassified production route: ' . $route->name();
                continue;
            }
            ++$classified;
            $this->verifyPolicy($route, $policy, $catalog, $errors);
            foreach ($route->methods() as $method) {
                if ($method === HttpMethod::GET || $method === HttpMethod::HEAD || $method === HttpMethod::OPTIONS) {
                    continue;
                }
                ++$mutations;
                if ($policy->csrfAction !== null) {
                    ++$csrfProtected;
                } else {
                    $errors[] = 'Browser mutation has no CSRF action: ' . $route->name();
                }
            }
        }
        foreach (array_keys($policies) as $name) {
            $found = false;
            foreach ($routes as $route) {
                if ($route->name() === $name) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $errors[] = 'Route-security policy has no registered route: ' . $name;
            }
        }

        return new RouteSecurityVerificationReport(count($routes), $classified, $mutations, $csrfProtected, $errors);
    }

    /** @param list<string> $errors */
    private function verifyPolicy(
        Route $route,
        RouteSecurityPolicy $policy,
        \Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationCatalog $catalog,
        array &$errors,
    ): void {
        if ($policy->classification->requiresAuthentication() && $policy->classification === RouteSecurityClassification::PUBLIC) {
            $errors[] = 'Protected route is incorrectly classified public: ' . $route->name();
        }
        if ($policy->requiresTenantContext && !$policy->classification->requiresAuthentication()) {
            $errors[] = 'Tenant route must require authentication: ' . $route->name();
        }
        if ($policy->classification === RouteSecurityClassification::BASE_ROLE_REQUIRED) {
            if ($policy->permissionCode === null || $policy->requiredAssurance === null) {
                $errors[] = 'Base-role route lacks exact authorization metadata: ' . $route->name();
            } else {
                $permission = $catalog->permission(new PermissionCode($policy->permissionCode));
                if ($permission === null || $permission->requiredAssurance->value !== $policy->requiredAssurance) {
                    $errors[] = 'Base-role route authorization metadata drifts from the catalog: ' . $route->name();
                }
            }
        }
        if ($policy->stepUpAction !== null) {
            $stepUp = StepUpAction::tryFrom($policy->stepUpAction);
            if ($stepUp === null) {
                $errors[] = 'Route references an unknown step-up action: ' . $route->name();
            } elseif (
                $policy->requiredAssurance !== null
                && !$stepUp->requirement()->satisfies(
                    AuthenticationAssuranceLevel::from($policy->requiredAssurance),
                )
            ) {
                $errors[] = 'Route step-up assurance is weaker than the declared route requirement: ' . $route->name();
            }
        }
        if ($policy->csrfAction !== null && CsrfAction::tryFrom($policy->csrfAction) === null) {
            $errors[] = 'Route references an unknown CSRF action: ' . $route->name();
        }
        if (
            $policy->classification === RouteSecurityClassification::PUBLIC
            && str_contains(strtolower($route->controller()::class), 'administr')
        ) {
            $errors[] = 'Public route points to an administrative controller: ' . $route->name();
        }
        if (str_contains($route->pattern()->value(), '{challengeId}') && !$policy->noStore) {
            $errors[] = 'Token-bearing route must declare no-store: ' . $route->name();
        }
    }
}
