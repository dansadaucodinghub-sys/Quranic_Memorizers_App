<?php

declare(strict_types=1);

namespace Qmdb\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class P3OrganizationAffiliationSecurityArchitectureTest extends TestCase
{
    public function testAffiliationModuleUsesPrivateTenantScopedBoundaries(): void
    {
        $root = dirname(__DIR__, 2);
        $module = (string) file_get_contents($root . '/src/Bootstrap/Module/OrganizationsAffiliationsModule.php');
        $repository = (string) file_get_contents($root . '/src/Modules/OrganizationAffiliations/Infrastructure/Persistence/MySqlOrganizationAffiliationRepository.php');
        $controller = (string) file_get_contents($root . '/src/Modules/OrganizationAffiliations/Interface/Http/OrganizationAffiliationsController.php');

        self::assertStringContainsString("organizations.affiliations", $module);
        self::assertStringContainsString("new ModuleId('organizations.registry')", $module);
        self::assertStringContainsString("new ModuleId('people.profiles')", $module);
        self::assertStringNotContainsString('security.privileged_access', $module);
        self::assertStringContainsString('workspace_id=:workspace_id', $repository);
        self::assertStringContainsString('BIN_TO_UUID', $repository);
        self::assertStringNotContainsString('UUID_FROM_BIN', $repository);
        self::assertStringContainsString('IdentityCsrf', $controller);
        self::assertStringContainsString('validates', $controller);
        self::assertStringContainsString('data-qmdb-progressive-form', $controller);
        self::assertStringContainsString('data-qmdb-modal', $controller);
        self::assertStringContainsString('text/vnd.qmdb.fragment+html', $controller);
        self::assertStringNotContainsString('localStorage', $controller);
        self::assertStringNotContainsString('person.search', strtolower($controller));
    }

    public function testEveryAffiliationMutationIsClosedCatalogCsrfAndIdempotencyClassified(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $catalog = (string) file_get_contents($root . '/src/Shared/Http/Routing/Security/ProductionRouteSecurityPolicyCatalog.php');
        foreach (
            [
            'workspace.organizations.affiliations.request.submit',
            'workspace.organizations.affiliations.assignments.submit',
            'workspace.organizations.affiliations.withdraw.submit',
            'workspace.organizations.affiliations.suspend.submit',
            'workspace.organizations.affiliations.resume.submit',
            'workspace.organizations.affiliations.end.submit',
            'account.affiliations.accept.submit',
            'account.affiliations.decline.submit',
            'account.affiliations.leave.submit',
            ] as $route
        ) {
            self::assertStringContainsString($route, $routes);
            self::assertStringContainsString($route, $catalog);
        }
        self::assertStringContainsString('ORGANIZATION_AFFILIATION_ACCEPT', $catalog);
        self::assertStringContainsString('ORGANIZATION_AFFILIATION_LEAVE', $catalog);
        self::assertStringContainsString('ORGANIZATION_AFFILIATION_END', $catalog);
    }
}
