<?php

declare(strict_types=1);

namespace Qmdb\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class CompetitionP13ArchitectureTest extends TestCase
{
    public function testP13IsTheFinalModuleBeforeHttpAndConsoleComposition(): void
    {
        $source = $this->read('src/Bootstrap/ApplicationFactory.php');
        $p12 = strpos($source, 'new ProductionHardeningModule()');
        $p13 = strpos($source, 'new PilotOfflineRolloutModule()');
        $http = strpos($source, 'new ApplicationHttpModule($this->projectRoot)');
        $console = strpos($source, 'new ConsoleFoundationModule()');
        self::assertIsInt($p12);
        self::assertIsInt($p13);
        self::assertIsInt($http);
        self::assertIsInt($console);
        self::assertLessThan($p13, $p12);
        self::assertLessThan($http, $p13);
        self::assertLessThan($console, $p13);
    }

    public function testPriorModulesDoNotDependOnP13(): void
    {
        foreach (glob(dirname(__DIR__, 2) . '/src/Bootstrap/Module/*.php') ?: [] as $file) {
            if (str_ends_with($file, 'PilotOfflineRolloutModule.php') || str_ends_with($file, 'ApplicationHttpModule.php') || str_ends_with($file, 'ConsoleFoundationModule.php')) {
                continue;
            }
            $source = file_get_contents($file);
            self::assertIsString($source);
            self::assertStringNotContainsString("new ModuleId('pilot.offline_rollout')", $source, $file);
        }
    }

    public function testControllersContainNoSqlOrPrivateKeyGeneration(): void
    {
        foreach (['P13PortalController.php', 'OfflineProtocolController.php'] as $name) {
            $source = $this->read('src/Modules/PilotOfflineRollout/Interface/Http/' . $name);
            self::assertStringNotContainsString('->prepare(', $source);
            self::assertStringNotContainsString('->query(', $source);
            self::assertStringNotContainsString('new PDO', $source);
            self::assertStringNotContainsString('sodium_crypto_sign_keypair', $source);
        }
    }

    public function testP13RoutesViewsAndOfflineAssetsAreRegistered(): void
    {
        $routes = $this->read('routes/web.php');
        $security = $this->read('src/Shared/Http/Routing/Security/ProductionRouteSecurityPolicyCatalog.php');
        foreach (['platform.pilots.index', 'platform.rollouts.index', 'workspace.offline_devices.index', 'workspace.offline_packages.index', 'workspace.offline_sync.index', 'workspace.offline_conflicts.index', 'offline.v1.sync.change'] as $route) {
            self::assertStringContainsString("'{$route}'", $routes);
            self::assertStringContainsString("'{$route}'", $security);
        }
        self::assertFileExists(dirname(__DIR__, 2) . '/public/assets/js/offline-venue-controller.js');
        self::assertFileExists(dirname(__DIR__, 2) . '/public/offline-service-worker.js');
    }

    public function testNoLaterPhaseSurfaceWasIntroduced(): void
    {
        foreach (['src/Modules/PilotOfflineRollout', 'src/Bootstrap/Module/PilotOfflineRolloutModule.php', 'routes/web.php'] as $relative) {
            $path = dirname(__DIR__, 2) . '/' . $relative;
            $source = is_dir($path) ? implode("\n", array_map(static fn (string $file): string => (string) file_get_contents($file), glob($path . '/*.php') ?: [])) : (string) file_get_contents($path);
            self::assertDoesNotMatchRegularExpression('/\bP1[4-9]\b|QMDB-P1[4-9]/', $source);
        }
    }

    private function read(string $relative): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/' . $relative);
        self::assertIsString($contents);

        return $contents;
    }
}
