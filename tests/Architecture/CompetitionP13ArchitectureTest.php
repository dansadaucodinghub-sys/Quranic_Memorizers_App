<?php

declare(strict_types=1);

namespace Qmdb\Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\PilotOfflineRollout\Application\OfflineOperationHandlerCatalog;
use Qmdb\Modules\PilotOfflineRollout\Application\P13RouteRuntimeCatalog;
use Qmdb\Modules\PilotOfflineRollout\Application\P13RuntimeCatalogVerifier;
use Qmdb\Tests\Support\PilotOfflineRollout\P13EvidenceCatalog;

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

    public function testRepositoryUsesDistinctBindingsForNativeMySqlPrepares(): void
    {
        $repository = $this->read('src/Modules/PilotOfflineRollout/Infrastructure/Persistence/MySqlPilotOfflineRolloutRepository.php');
        self::assertStringNotContainsString('IF(:target=', $repository);
        self::assertStringNotContainsString('a.id=:owner AND', $repository);
        self::assertStringNotContainsString('workspace_id=:workspace AND public_id=:venue', $repository);
        self::assertStringNotContainsString('FROM_UNIXTIME(:timestamp)', $repository);
        self::assertStringNotContainsString('IF(:status=', $repository);
        self::assertStringNotContainsString('first_local_sequence=COALESCE(first_local_sequence,:sequence),last_local_sequence=:sequence', $repository);
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

    public function testAllBrowserMutationsDelegateToConcreteApplicationService(): void
    {
        $controller = $this->read('src/Modules/PilotOfflineRollout/Interface/Http/P13PortalController.php');
        $service = $this->read('src/Modules/PilotOfflineRollout/Application/P13AdministrativeMutationService.php');
        self::assertStringContainsString('$this->mutations->execute(', $controller);
        self::assertStringNotContainsString('Unsupported P13 operation', $controller);
        self::assertStringNotContainsString('$this->repository->transition', $controller);
        foreach (\Qmdb\Modules\PilotOfflineRollout\Application\P13RouteRuntimeCatalog::BROWSER_MUTATIONS as $route) {
            self::assertStringContainsString("'{$route}'", $service, $route);
        }
    }

    public function testOfflineDispatcherHasPriorDomainAndNativeProductionAdapters(): void
    {
        $module = $this->read('src/Bootstrap/Module/PilotOfflineRolloutModule.php');
        $repository = $this->read('src/Modules/PilotOfflineRollout/Infrastructure/Persistence/MySqlPilotOfflineRolloutRepository.php');
        foreach (['P7OfflineParticipantOperationAdapter', 'P6OfflineScoreDraftAdapter', 'P6OfflineScoreSheetSubmissionAdapter', 'P13NativeOfflineOperationAdapter', 'OfflineOperationDispatcher'] as $type) {
            self::assertStringContainsString($type . '::class', $module);
        }
        self::assertStringNotContainsString('AUTHORITATIVE_REVALIDATION_REQUIRED', $repository);
        self::assertStringContainsString('$this->dispatcher->dispatch(', $repository);
    }

    public function testAdministrativeMutationStepUpDomainAuditAndCompletionAreTransactionallyOrdered(): void
    {
        $controller = $this->read('src/Modules/PilotOfflineRollout/Interface/Http/P13PortalController.php');
        $transaction = strpos($controller, '$this->transactions->transactional(');
        $stepUp = strpos($controller, '$this->stepUp->consume(', $transaction === false ? 0 : $transaction);
        $mutation = strpos($controller, '$this->mutations->execute(', $transaction === false ? 0 : $transaction);
        $completion = strpos($controller, '$this->repository->completeOperation(', $transaction === false ? 0 : $transaction);
        self::assertIsInt($transaction);
        self::assertIsInt($stepUp);
        self::assertIsInt($mutation);
        self::assertIsInt($completion);
        self::assertLessThan($stepUp, $transaction);
        self::assertLessThan($mutation, $stepUp);
        self::assertLessThan($completion, $mutation);
    }

    public function testRuntimeVerifierRejectsRouteAndHandlerCatalogRegression(): void
    {
        $verifier = new P13RuntimeCatalogVerifier();
        $routes = (new P13RouteRuntimeCatalog())->entries();
        $verifier->verifyRoutes($routes);
        $routeRegressions = [];
        $missing = $routes;
        unset($missing['platform.pilots.create']);
        $routeRegressions[] = $missing;
        $invalidController = $routes;
        $invalidController['platform.pilots.create']['controller'] = 'Qmdb\\Missing\\Controller';
        $routeRegressions[] = $invalidController;
        $invalidService = $routes;
        $invalidService['platform.pilots.create']['application_service'] = 'Qmdb\\Missing\\Service';
        $routeRegressions[] = $invalidService;
        $missingCsrf = $routes;
        $missingCsrf['platform.pilots.create']['csrf_action'] = null;
        $routeRegressions[] = $missingCsrf;
        $getMutation = $routes;
        $getMutation['platform.pilots.create']['method'] = 'GET';
        $routeRegressions[] = $getMutation;
        foreach ($routeRegressions as $regression) {
            try {
                $verifier->verifyRoutes($regression);
                self::fail('A route-catalog regression passed verification.');
            } catch (\RuntimeException) {
                self::addToAssertionCount(1);
            }
        }

        $handlers = (new OfflineOperationHandlerCatalog())->entries();
        $registrations = array_map(static fn (array $entry): string => $entry['owner'], $handlers);
        $verifier->verifyHandlers($handlers, $registrations);
        foreach (['PARTICIPANT_CHECK_IN', 'SCORE_DRAFT_SAVED', 'VENUE_INCIDENT_RECORDED'] as $operation) {
            $missingHandler = $handlers;
            $missingRegistration = $registrations;
            unset($missingHandler[$operation], $missingRegistration[$operation]);
            try {
                $verifier->verifyHandlers($missingHandler, $missingRegistration);
                self::fail('A missing offline handler passed verification: ' . $operation);
            } catch (\RuntimeException) {
                self::addToAssertionCount(1);
            }
        }
        $ownership = $handlers;
        $ownership['PARTICIPANT_CHECK_IN'] = array_replace(
            $ownership['PARTICIPANT_CHECK_IN'],
            ['owner' => 'P13_OFFLINE'],
        );
        try {
            $verifier->verifyHandlers($ownership, $registrations);
            self::fail('An invalid handler ownership declaration passed verification.');
        } catch (\RuntimeException) {
            self::addToAssertionCount(1);
        }
    }

    public function testEvidenceCatalogCoversEveryRequiredCapabilityAndResolvesTests(): void
    {
        $evidence = new P13EvidenceCatalog();
        $mutations = $evidence->mutations();
        self::assertCount(40, $mutations);
        self::assertSame(P13RouteRuntimeCatalog::MUTATIONS, array_column($mutations, 'route'));
        foreach ($mutations as $entry) {
            $this->assertPhpTestIdentifier($entry['positive_http']);
            $this->assertPhpTestIdentifier($entry['mysql']);
        }
        self::assertCount(12, $evidence->concurrency());
        foreach ($evidence->concurrency() as $identifier) {
            $this->assertPhpTestIdentifier($identifier);
        }
        self::assertCount(42, $evidence->faults());
        foreach ($evidence->faults() as $identifier) {
            $this->assertPhpTestIdentifier($identifier);
        }
        foreach (array_merge($evidence->frontend(), $evidence->accessibility()) as $identifier) {
            [$file, $test] = explode('#', $identifier, 2);
            $source = $this->read($file);
            self::assertStringContainsString("test('" . $test . "'", $source, $identifier);
        }
    }

    public function testFaultInjectionRemainsTestOnlyAndOutsideReleaseRuntime(): void
    {
        foreach (['src/Bootstrap/Module/PilotOfflineRolloutModule.php', 'routes/web.php', 'src/Modules/PilotOfflineRollout/Interface/Console/CompetitionP13VerifyConsoleCommand.php'] as $file) {
            self::assertStringNotContainsString('P13_TEST_FAULT', $this->read($file), $file);
            self::assertStringNotContainsString('FaultInject', $this->read($file), $file);
        }
        $release = $this->read('tools/Policy/PathPolicy.php');
        self::assertStringContainsString('|tests|', $release);
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

    private function assertPhpTestIdentifier(string $identifier): void
    {
        [$callable] = explode('#', $identifier, 2);
        [$class, $method] = explode('::', $callable, 2);
        self::assertTrue(class_exists($class), $identifier);
        self::assertTrue(method_exists($class, $method), $identifier);
        self::assertTrue((new \ReflectionMethod($class, $method))->isPublic(), $identifier);
    }
}
