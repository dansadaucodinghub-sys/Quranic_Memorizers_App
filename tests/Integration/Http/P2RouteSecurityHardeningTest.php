<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Bootstrap\Http\HttpRuntime;
use Qmdb\Shared\Presentation\Response\FragmentRequestDetector;
use Qmdb\Tests\Support\Http\HttpTestFactory;
use Qmdb\Tests\Support\Http\ProductionHttpRuntimeFactory;

#[\PHPUnit\Framework\Attributes\Group('SecurityHardening')]
#[\PHPUnit\Framework\Attributes\Group('RouteSecurity')]
final class P2RouteSecurityHardeningTest extends TestCase
{
    private const string IDENTIFIER = '019c0000-0000-7000-8000-000000000001';

    private static ?HttpRuntime $runtime = null;

    /** @return iterable<string, array{string, string, string}> */
    public static function protectedRoutes(): iterable
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/routes/web.php');
        self::assertIsString($source);
        preg_match_all(
            "/new Route\\(\\s*'(?<name>[^']+)'\\s*,\\s*\\[(?<methods>[^\\]]+)\\]\\s*,\\s*new RoutePattern\\('(?<path>[^']+)'\\)/s",
            $source,
            $routes,
            PREG_SET_ORDER,
        );
        self::assertNotSame([], $routes);
        $public = array_fill_keys([
            'system.home', 'system.about.page', 'system.status.page', 'system.health.live', 'system.health.ready',
            'api.v1.system.about', 'account.registration.form', 'account.registration.submit',
            'account.registration.accepted', 'account.email_verification.resend.form',
            'account.email_verification.resend.submit', 'account.email_verification.completed',
            'account.email_verification.form', 'account.email_verification.submit', 'account.login.form',
            'account.login.submit', 'account.password_recovery.request.form',
            'account.password_recovery.request.submit', 'account.password_recovery.request.accepted',
            'account.password_recovery.reset.form', 'account.password_recovery.reset.submit',
            'account.password_recovery.reset.completed', 'account.mfa.login.form', 'account.mfa.login.totp',
            'account.mfa.login.recovery_code', 'account.mfa.login.passkey.options',
            'account.mfa.login.passkey.verify', 'account.passkey.login.options', 'account.passkey.login.verify',
            'geography.nigeria.index', 'geography.nigeria.area', 'geography.lookup.children',
            'quran.public.home', 'quran.public.surahs', 'quran.public.surah', 'quran.public.ayah',
            'quran.public.partition', 'quran.public.sajdahs', 'quran.public.search',
        ], true);
        foreach ($routes as $route) {
            $name = $route['name'];
            if (isset($public[$name])) {
                continue;
            }
            preg_match_all('/HttpMethod::([A-Z]+)/', $route['methods'], $methods);
            self::assertNotSame([], $methods[1], 'Route must declare at least one HTTP method: ' . $name);
            $path = preg_replace('/\\{[^}]+\\}/', self::IDENTIFIER, $route['path']);
            self::assertIsString($path);
            foreach ($methods[1] as $method) {
                yield $name . ' ' . $method => [$name, $method, $path];
            }
        }
    }

    #[DataProvider('protectedRoutes')]
    public function testEveryProtectedRouteRejectsAnonymousFullAndFragmentRequests(
        string $routeName,
        string $method,
        string $path,
    ): void {
        $response = self::runtime()->handle(HttpTestFactory::request($method, $path));

        self::assertContains($response->getStatusCode(), [303, 403], $routeName);
        if ($response->getStatusCode() === 303) {
            self::assertSame('/login', $response->getHeaderLine('Location'), $routeName);
        }
        self::assertStringNotContainsString('session_public_id', (string) $response->getBody(), $routeName);

        $fragment = self::runtime()->handle(HttpTestFactory::request($method, $path)->withHeader(
            'Accept',
            FragmentRequestDetector::MEDIA_TYPE,
        ));

        self::assertContains($fragment->getStatusCode(), [401, 403], $routeName);
        if ($fragment->getStatusCode() === 401) {
            self::assertSame('/login', $fragment->getHeaderLine('X-QMDB-Navigate'), $routeName);
            self::assertStringContainsString('AUTHENTICATION_REQUIRED', (string) $fragment->getBody(), $routeName);
        }
        self::assertStringNotContainsString('session_public_id', (string) $fragment->getBody(), $routeName);
    }

    private static function runtime(): HttpRuntime
    {
        return self::$runtime ??= ProductionHttpRuntimeFactory::create();
    }
}
