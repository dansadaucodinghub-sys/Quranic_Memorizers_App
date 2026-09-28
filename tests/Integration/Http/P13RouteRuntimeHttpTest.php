<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Bootstrap\Http\HttpRuntime;
use Qmdb\Modules\PilotOfflineRollout\Application\P13RouteRuntimeCatalog;
use Qmdb\Shared\Presentation\Response\FragmentRequestDetector;
use Qmdb\Tests\Support\Http\HttpTestFactory;
use Qmdb\Tests\Support\Http\ProductionHttpRuntimeFactory;

#[\PHPUnit\Framework\Attributes\Group('P13RouteRuntime')]
final class P13RouteRuntimeHttpTest extends TestCase
{
    private const string IDENTIFIER = '019c0000-0000-7000-8000-000000000013';

    private static ?HttpRuntime $runtime = null;

    /** @return iterable<string,array{string,string,string}> */
    public static function routes(): iterable
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/routes/web.php');
        self::assertIsString($source);
        preg_match_all(
            "/new Route\(\s*'(?<name>(?:platform\.(?:pilots|rollouts|rollout_waves)|workspace\.offline_|offline\.v1\.)[^']+)'\s*,\s*\[HttpMethod::(?<method>[A-Z]+)\]\s*,\s*new RoutePattern\('(?<path>[^']+)'\)/",
            $source,
            $matches,
            PREG_SET_ORDER,
        );
        self::assertCount(62, $matches);
        self::assertSame(P13RouteRuntimeCatalog::ROUTES, array_column($matches, 'name'));
        foreach ($matches as $route) {
            $path = preg_replace('/\{[^}]+\}/', self::IDENTIFIER, $route['path']);
            self::assertIsString($path);
            yield $route['name'] => [$route['name'], $route['method'], $path];
        }
    }

    #[DataProvider('routes')]
    public function testEveryP13RouteResolvesAndFailsClosedWithoutAuthority(string $name, string $method, string $path): void
    {
        $response = self::runtime()->handle(HttpTestFactory::request($method, $path));
        self::assertNotContains($response->getStatusCode(), [404, 500, 501], $name);
        self::assertStringContainsString('no-store', $response->getHeaderLine('Cache-Control'), $name);
        self::assertStringNotContainsString('Unsupported P13 operation', (string) $response->getBody(), $name);

        if (!str_starts_with($name, 'offline.v1.')) {
            $fragment = self::runtime()->handle(HttpTestFactory::request($method, $path)->withHeader('Accept', FragmentRequestDetector::MEDIA_TYPE));
            self::assertNotContains($fragment->getStatusCode(), [404, 500, 501], $name);
            self::assertStringNotContainsString('csrf_token', (string) $fragment->getBody(), $name);
        }
    }

    public function testEveryP13MutationDeclaresConcretePositiveContract(): void
    {
        $entries = (new P13RouteRuntimeCatalog())->entries();
        self::assertCount(40, P13RouteRuntimeCatalog::MUTATIONS);
        foreach (P13RouteRuntimeCatalog::MUTATIONS as $route) {
            $entry = $entries[$route] ?? null;
            self::assertIsArray($entry, $route);
            self::assertSame('POST', $entry['method'], $route);
            self::assertNotSame('RUNTIME_ROUTE_REGISTRY_REQUIRED', $entry['path'], $route);
            self::assertTrue(class_exists((string) $entry['controller']), $route);
            self::assertTrue(method_exists((string) $entry['controller'], (string) $entry['controller_method']), $route);
            self::assertTrue(class_exists((string) $entry['application_service']), $route);
            self::assertSame('PRIVATE_NO_STORE', $entry['cache_policy'], $route);
            self::assertNotSame('', $entry['runtime_capability'], $route);
            if (!str_starts_with($route, 'offline.v1.')) {
                self::assertNotNull($entry['permission'], $route);
                self::assertNotNull($entry['assurance'], $route);
                self::assertNotNull($entry['step_up_action'], $route);
                self::assertNotNull($entry['csrf_action'], $route);
                self::assertTrue($entry['idempotency'], $route);
                self::assertNotNull($entry['full_page_fallback'], $route);
                self::assertTrue($entry['fragment_support'], $route);
            }
        }
    }

    private static function runtime(): HttpRuntime
    {
        return self::$runtime ??= ProductionHttpRuntimeFactory::create();
    }
}
