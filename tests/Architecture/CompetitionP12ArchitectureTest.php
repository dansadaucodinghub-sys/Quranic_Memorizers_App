<?php

declare(strict_types=1);

namespace Qmdb\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class CompetitionP12ArchitectureTest extends TestCase
{
    public function testP12IsRegisteredBeforeHttpAndConsoleComposition(): void
    {
        $source = $this->read('src/Bootstrap/ApplicationFactory.php');
        $p12 = strpos($source, 'new ProductionHardeningModule()');
        $http = strpos($source, 'new ApplicationHttpModule($this->projectRoot)');
        $console = strpos($source, 'new ConsoleFoundationModule()');

        self::assertIsInt($p12);
        self::assertIsInt($http);
        self::assertIsInt($console);
        self::assertLessThan($http, $p12);
        self::assertLessThan($console, $p12);
    }

    public function testP12DoesNotStartOfflineVenueOrP13Capabilities(): void
    {
        $module = strtolower($this->read('src/Bootstrap/Module/ProductionHardeningModule.php'));
        $migrations = strtolower($this->read('database/migrations.php'));

        self::assertStringNotContainsString('offlinepackage', $module . $migrations);
        self::assertStringNotContainsString('offlinesync', $module . $migrations);
        self::assertStringNotContainsString('pilotrollout', $module . $migrations);
        self::assertStringNotContainsString('p13', $module . $migrations);
    }

    public function testP12RoutesAndViewsUseClosedRegisteredSurfaces(): void
    {
        $routes = $this->read('routes/web.php');
        $security = $this->read('src/Shared/Http/Routing/Security/ProductionRouteSecurityPolicyCatalog.php');
        foreach (
            ['account.notifications', 'account.privacy', 'workspace.integrations', 'workspace.privacy',
            'platform.integrations', 'platform.privacy', 'platform.operations', 'platform.audit.integrity',
            'api.v1.results.index'] as $route
        ) {
            self::assertStringContainsString("'{$route}'", $routes);
            self::assertStringContainsString("'{$route}'", $security);
        }

        $presentation = $this->read('src/Bootstrap/Module/PresentationFoundationModule.php');
        self::assertStringContainsString("'pages.p12-portal'", $presentation);
        self::assertStringContainsString("'fragments.p12-portal'", $presentation);
    }

    public function testP12ControllerDoesNotOwnSecretsSqlOrProviderIo(): void
    {
        $controller = $this->read('src/Modules/ProductionHardening/Interface/Http/P12PortalController.php');

        self::assertStringNotContainsString('random_bytes(', $controller);
        self::assertStringNotContainsString('new PDO', $controller);
        self::assertStringNotContainsString('->query(', $controller);
        self::assertStringNotContainsString('->prepare(', $controller);
        self::assertStringNotContainsString('WebhookTransport', $controller);
    }

    public function testWebhookProviderCallIsOutsideTheClaimTransaction(): void
    {
        $service = $this->read('src/Modules/ProductionHardening/Infrastructure/Persistence/P12MaintenanceService.php');
        $method = strpos($service, 'public function processWebhooks');
        self::assertIsInt($method);
        $claimCommit = strpos($service, '$database->commit();', $method);
        $deliveryBoundary = strpos($service, '$this->deliverClaimedWebhook', $method);
        self::assertIsInt($deliveryBoundary);
        $transportCall = strpos($service, '$this->transport->send', $deliveryBoundary);

        self::assertIsInt($claimCommit);
        self::assertIsInt($transportCall);
        self::assertLessThan($deliveryBoundary, $claimCommit);
        self::assertLessThan($transportCall, $deliveryBoundary);
    }

    private function read(string $relative): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/' . $relative);
        self::assertIsString($contents);
        return $contents;
    }
}
