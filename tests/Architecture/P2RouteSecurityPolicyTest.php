<?php

declare(strict_types=1);

namespace Qmdb\Tests\Architecture;

use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Routing\HttpMethod;
use Qmdb\Shared\Http\Routing\Route;
use Qmdb\Shared\Http\Routing\RouteCollection;
use Qmdb\Shared\Http\Routing\RoutePattern;
use Qmdb\Shared\Http\Routing\Security\ProductionRouteSecurityPolicyCatalog;
use Qmdb\Shared\Http\Routing\Security\RouteSecurityVerifier;

#[Group('SecurityHardening')]
#[Group('RouteSecurity')]
final class P2RouteSecurityPolicyTest extends TestCase
{
    public function testEveryRegisteredProductionRouteHasAClosedSecurityPolicy(): void
    {
        $report = $this->verifier()->verify($this->productionRoutes());

        self::assertTrue($report->isValid(), implode(', ', $report->errors));
        self::assertSame(126, $report->routeCount);
        self::assertSame(126, $report->classifiedRouteCount);
        self::assertSame(59, $report->mutationRouteCount);
        self::assertSame(59, $report->csrfProtectedMutationCount);
    }

    public function testAnUnclassifiedRouteFailsTheClosedPolicyVerifier(): void
    {
        $routes = iterator_to_array($this->productionRoutes());
        $routes[] = new Route(
            'account.security.unknown',
            [HttpMethod::GET],
            new RoutePattern('/account/security/unknown'),
            $this->controller(),
        );
        $report = $this->verifier()->verify(new RouteCollection(...$routes));

        self::assertFalse($report->isValid());
        self::assertContains('Unclassified production route: account.security.unknown', $report->errors);
    }

    private function verifier(): RouteSecurityVerifier
    {
        return new RouteSecurityVerifier(new ProductionRouteSecurityPolicyCatalog());
    }

    private function productionRoutes(): RouteCollection
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        self::assertIsString($source);
        preg_match_all(
            "/new Route\\(\\s*'(?<name>[^']+)'\\s*,\\s*\\[(?<methods>[^\\]]+)\\]\\s*,\\s*new RoutePattern\\('(?<path>[^']+)'\\)/s",
            $source,
            $matches,
            PREG_SET_ORDER,
        );
        self::assertCount(126, $matches);
        $routes = [];
        foreach ($matches as $match) {
            preg_match_all('/HttpMethod::([A-Z]+)/', $match['methods'], $methods);
            self::assertNotSame([], $methods[1]);
            $routes[] = new Route(
                $match['name'],
                array_map(static fn (string $method): HttpMethod => HttpMethod::from($method), $methods[1]),
                new RoutePattern($match['path']),
                $this->controller(),
            );
        }

        return new RouteCollection(...$routes);
    }

    private function controller(): Controller
    {
        return new class implements Controller {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(204);
            }
        };
    }
}
