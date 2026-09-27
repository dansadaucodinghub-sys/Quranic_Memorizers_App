<?php

declare(strict_types=1);

namespace Qmdb\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class CompetitionP11ArchitectureTest extends TestCase
{
    public function testP11IsRegisteredBeforeHttpAndConsoleComposition(): void
    {
        $source = $this->read('src/Bootstrap/ApplicationFactory.php');
        $p11 = strpos($source, 'new SearchAnalyticsReportingModule($this->projectRoot)');
        $http = strpos($source, 'new ApplicationHttpModule($this->projectRoot)');
        $console = strpos($source, 'new ConsoleFoundationModule()');

        self::assertIsInt($p11);
        self::assertIsInt($http);
        self::assertIsInt($console);
        self::assertLessThan($http, $p11);
        self::assertLessThan($console, $p11);
    }

    public function testP11DoesNotImplementP12OrOfflineVenueCapabilities(): void
    {
        $module = $this->read('src/Bootstrap/Module/SearchAnalyticsReportingModule.php');
        $migrations = $this->read('database/migrations.php');
        $combined = strtolower($module . $migrations);

        self::assertStringNotContainsString('offlinepackage', $combined);
        self::assertStringNotContainsString('offlinesync', $combined);
        self::assertStringNotContainsString('productionhardeningmodule', $combined);
    }

    public function testP11RoutesAreClosedAndSecurityClassified(): void
    {
        $routes = $this->read('routes/web.php');
        $security = $this->read('src/Shared/Http/Routing/Security/ProductionRouteSecurityPolicyCatalog.php');
        foreach (
            ['public.search', 'public.statistics', 'workspace.search', 'workspace.analytics',
            'workspace.reports', 'workspace.reports.request', 'workspace.export.download',
            'workspace.reports.approvals', 'workspace.reports.approve',
            'platform.search', 'platform.analytics', 'platform.reports', 'platform.reports.request',
            'platform.reports.approvals', 'platform.reports.approve',
            'platform.export.download'] as $route
        ) {
            self::assertStringContainsString("'{$route}'", $routes);
            self::assertStringContainsString("'{$route}'", $security);
        }
    }

    public function testP11ViewsAreRegisteredAndConsumeRepositoryRowsAsLists(): void
    {
        $presentation = $this->read('src/Bootstrap/Module/PresentationFoundationModule.php');
        self::assertStringContainsString("'pages.p11-portal'", $presentation);
        self::assertStringContainsString("'fragments.p11-portal'", $presentation);

        $fragment = $this->read('resources/views/fragments/p11-portal.php');
        foreach (['items', 'dashboards', 'definitions', 'runs', 'approvals'] as $key) {
            self::assertStringContainsString("\$view->list('{$key}')", $fragment);
            self::assertStringNotContainsString("\$view->array('{$key}')", $fragment);
        }
    }

    private function read(string $relative): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/' . $relative);
        self::assertIsString($contents);
        return $contents;
    }
}
