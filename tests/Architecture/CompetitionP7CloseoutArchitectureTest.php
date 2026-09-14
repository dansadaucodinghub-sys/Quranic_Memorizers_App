<?php

declare(strict_types=1);

namespace Qmdb\Tests\Architecture;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Http\Routing\Security\ProductionRouteSecurityPolicyCatalog;
use Qmdb\Shared\Http\Routing\Security\RouteSecurityClassification;

#[Group('P7Closeout')]
#[Group('P7Concurrency')]
#[Group('P7FaultInjection')]
#[Group('P7Accessibility')]
final class CompetitionP7CloseoutArchitectureTest extends TestCase
{
    public function testP7CloseoutIsARegisteredReadOnlyComposition(): void
    {
        $root = dirname(__DIR__, 2);
        $module = $this->read($root . '/src/Bootstrap/Module/ConsoleFoundationModule.php');
        $check = $this->read($root . '/src/Modules/CompetitionLive/Infrastructure/Readiness/CompetitionP7CloseoutReadinessCheck.php');
        $command = $this->read($root . '/src/Modules/CompetitionLive/Interface/Console/CompetitionP7CloseoutVerifyConsoleCommand.php');

        self::assertStringContainsString('CompetitionP7CloseoutReadinessCheck', $module);
        self::assertStringContainsString('CompetitionP7CloseoutVerifyConsoleCommand', $module);
        self::assertStringContainsString("competition:p7:closeout:verify", $command);
        self::assertStringContainsString('RouteSecurityVerifier', $check);
        self::assertStringContainsString('ScheduledTaskMap', $check);
        self::assertStringContainsString('REQUIRED_TRIGGERS', $check);
        self::assertStringNotContainsString('INSERT INTO', $check);
        self::assertStringNotContainsString('UPDATE competition', $check);
    }

    public function testP7MutationRoutesHaveClosedTenantCsrfAndIdempotencyPolicies(): void
    {
        $routes = $this->read(dirname(__DIR__, 2) . '/routes/web.php');
        preg_match_all("/'(workspace\\.competition\\.(?:live_|result_publication\\.|appeal_adjudication\\.)[^']+)'/", $routes, $matches);
        $names = array_values(array_unique($matches[1]));
        self::assertCount(56, $names);
        $policies = (new ProductionRouteSecurityPolicyCatalog())->policies();
        foreach ($names as $name) {
            $policy = $policies[$name] ?? null;
            self::assertNotNull($policy, $name);
            self::assertTrue($policy->classification->requiresAuthentication(), $name);
            self::assertTrue($policy->requiresTenantContext, $name);
            self::assertTrue($policy->noStore, $name);
            if (!str_ends_with($name, '.form')) {
                self::assertNotNull($policy->csrfAction, $name);
                self::assertTrue($policy->requiresIdempotency, $name);
            }
        }
    }

    public function testPublicP7LiveProjectionIsReadOnlyAndPrivateFormsRemainAccessible(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = $this->read($root . '/routes/web.php');
        $public = $this->read($root . '/src/Modules/CompetitionLive/Interface/Http/CompetitionPublicLiveController.php');
        foreach (['competition.public.live', 'competition.public.live.snapshot', 'competition.public.live.stream'] as $route) {
            self::assertStringContainsString("new Route('{$route}', [HttpMethod::GET]", $routes);
        }
        self::assertStringContainsString('aria-live="polite"', $public);
        self::assertStringContainsString('htmlspecialchars', $public);
        self::assertStringContainsString("'text/event-stream; charset=utf-8'", $public);
        self::assertStringContainsString("'Cache-Control', 'no-store'", $public);

        foreach (
            [
            '/src/Modules/CompetitionLive/Interface/Http/CompetitionLiveSessionWorkflowController.php',
            '/src/Modules/CompetitionPublication/Interface/Http/CompetitionResultPublicationWorkflowController.php',
            '/src/Modules/CompetitionAppealAdjudication/Interface/Http/CompetitionAppealAdjudicationController.php',
            ] as $file
        ) {
            $controller = $this->read($root . $file);
            self::assertStringContainsString('<main><h1>', $controller);
            self::assertStringContainsString('aria-describedby=', $controller);
            self::assertStringContainsString('csrf_token', $controller);
            self::assertStringContainsString('submission_id', $controller);
            self::assertStringContainsString('private, no-store', $controller);
        }
    }

    public function testP7ConcurrencyAndRecoverySourcesUseLocksTransactionsAndBoundedWork(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (
            [
            '/src/Modules/CompetitionLive/Application/CompetitionLiveSessionWorkflowService.php',
            '/src/Modules/CompetitionPublication/Application/CompetitionResultPublicationWorkflowService.php',
            '/src/Modules/CompetitionAppealAdjudication/Application/CompetitionAppealAdjudicationService.php',
            ] as $file
        ) {
            $service = $this->read($root . $file);
            self::assertStringContainsString('transactional(', $service);
            self::assertStringContainsString('record(', $service);
            self::assertStringContainsString('audit', $service);
        }
        $liveRepository = $this->read($root . '/src/Modules/CompetitionLive/Infrastructure/Persistence/MySqlCompetitionLiveRuntimeRepository.php');
        self::assertStringContainsString('FOR UPDATE', $liveRepository);
        self::assertStringNotContainsString('MAX(sequence_number)', $liveRepository);

        foreach (
            [
            '/src/Modules/CompetitionLive/Infrastructure/Persistence/CompetitionP7LiveMaintenanceService.php',
            '/src/Modules/CompetitionPublication/Infrastructure/Persistence/CompetitionResultPublicationProjectionService.php',
            '/src/Modules/CompetitionAppealAdjudication/Infrastructure/Persistence/CompetitionAppealMaintenanceService.php',
            ] as $file
        ) {
            self::assertStringContainsString('assertLimit', $this->read($root . $file));
        }
    }

    private function read(string $path): string
    {
        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }
}
