<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Bootstrap\Http\HttpRuntime;
use Qmdb\Shared\Presentation\Response\FragmentRequestDetector;
use Qmdb\Tests\Support\Http\HttpTestFactory;
use Qmdb\Tests\Support\Http\ProductionHttpRuntimeFactory;

#[\PHPUnit\Framework\Attributes\Group('P7RouteSecurity')]
#[\PHPUnit\Framework\Attributes\Group('P7Accessibility')]
final class CompetitionP7RouteSecurityIntegrationTest extends TestCase
{
    private const string IDENTIFIER = '019c0000-0000-7000-8000-000000000001';

    private static ?HttpRuntime $runtime = null;

    /** @return iterable<string,array{string,string}> */
    public static function privateP7Routes(): iterable
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/routes/web.php');
        self::assertIsString($source);
        preg_match_all(
            "/new Route\\(\\s*'(?<name>workspace\\.competition\\.(?:live_|result_publication\\.|appeal_adjudication\\.)[^']+)'\\s*,\\s*\\[HttpMethod::(?<method>[A-Z]+)\\]\\s*,\\s*new RoutePattern\\('(?<path>[^']+)'\\)/",
            $source,
            $matches,
            PREG_SET_ORDER,
        );
        self::assertCount(56, $matches);
        foreach ($matches as $route) {
            $path = preg_replace('/\\{[^}]+\\}/', self::IDENTIFIER, $route['path']);
            self::assertIsString($path);
            yield $route['name'] . ' ' . $route['method'] => [$route['method'], $path];
        }
    }

    #[DataProvider('privateP7Routes')]
    public function testEveryPrivateP7RouteRejectsAnonymousFullAndFragmentRequests(string $method, string $path): void
    {
        $full = self::runtime()->handle(HttpTestFactory::request($method, $path));
        self::assertContains($full->getStatusCode(), [303, 403]);
        self::assertStringContainsString('no-store', $full->getHeaderLine('Cache-Control'));

        $fragment = self::runtime()->handle(HttpTestFactory::request($method, $path)->withHeader(
            'Accept',
            FragmentRequestDetector::MEDIA_TYPE,
        ));
        self::assertContains($fragment->getStatusCode(), [401, 403]);
        self::assertStringNotContainsString('csrf_token', (string) $fragment->getBody());
    }

    private static function runtime(): HttpRuntime
    {
        return self::$runtime ??= ProductionHttpRuntimeFactory::create();
    }
}
