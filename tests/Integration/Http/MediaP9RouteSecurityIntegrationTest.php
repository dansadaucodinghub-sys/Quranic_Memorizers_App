<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Bootstrap\Http\HttpRuntime;
use Qmdb\Shared\Http\Routing\Security\ProductionRouteSecurityPolicyCatalog;
use Qmdb\Shared\Presentation\Response\FragmentRequestDetector;
use Qmdb\Tests\Support\Http\{HttpTestFactory, ProductionHttpRuntimeFactory};

final class MediaP9RouteSecurityIntegrationTest extends TestCase
{
    private static ?HttpRuntime $runtime = null;

    /** @return iterable<string,array{string,string,string}> */
    public static function routes(): iterable
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/routes/web.php');
        self::assertIsString($source);
        preg_match_all("/new Route\\(\\s*'(workspace\\.media\\.[^']+)'\\s*,\\s*\\[HttpMethod::([A-Z]+)\\]\\s*,\\s*new RoutePattern\\('([^']+)'\\)/", $source, $matches, PREG_SET_ORDER);
        self::assertCount(20, $matches);
        foreach ($matches as $route) {
            yield $route[1] => [$route[1], $route[2], str_replace('{assetId}', '019c0000-0000-7000-8000-000000000001', $route[3])];
        }
    }

    #[DataProvider('routes')]
    public function testPrivateRoutesHaveClosedPoliciesAndRejectAnonymousRequests(string $name, string $method, string $path): void
    {
        $policies = (new ProductionRouteSecurityPolicyCatalog())->policies();
        self::assertArrayHasKey($name, $policies);
        self::assertTrue($policies[$name]->requiresTenantContext);
        self::assertTrue($policies[$name]->noStore);
        self::assertSame('PHISHING_RESISTANT', $policies[$name]->requiredAssurance);
        if ($method === 'POST') {
            self::assertNotNull($policies[$name]->csrfAction);
            self::assertTrue($policies[$name]->requiresIdempotency);
        }
        $runtime = self::$runtime ??= ProductionHttpRuntimeFactory::create();
        $full = $runtime->handle(HttpTestFactory::request($method, $path));
        self::assertContains($full->getStatusCode(), [303, 403]);
        self::assertStringContainsString('no-store', $full->getHeaderLine('Cache-Control'));
        $fragment = $runtime->handle(HttpTestFactory::request($method, $path)->withHeader('Accept', FragmentRequestDetector::MEDIA_TYPE));
        self::assertContains($fragment->getStatusCode(), [401, 403]);
        self::assertStringNotContainsString('csrf_token', (string) $fragment->getBody());
    }
}
