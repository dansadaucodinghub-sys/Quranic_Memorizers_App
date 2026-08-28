<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Tests\Support\Http\HttpTestFactory;
use Qmdb\Tests\Support\Http\ProductionHttpRuntimeFactory;

final class HttpRoutesTest extends TestCase
{
    /** @return iterable<string, array{string, array<string, string>}> */
    public static function productionRoutes(): iterable
    {
        $about = [
            'application' => 'QMDB',
            'name' => 'Qur’an Memorizer DB',
            'phase' => 'P2',
            'batch' => 'QMDB-P2-B06',
            'baseline' => 'QMDB-P0-FRZ-001',
            'status' => 'ready',
        ];

        yield 'liveness' => ['/health/live', ['status' => 'alive']];
        yield 'api about' => ['/api/v1/system/about', $about];
    }

    public function testReadinessFailureIsGenericWhenDatabaseIsUnavailable(): void
    {
        $response = ProductionHttpRuntimeFactory::create()->handle(
            HttpTestFactory::request('GET', '/health/ready'),
        );

        self::assertSame(503, $response->getStatusCode());
        self::assertSame(['status' => 'not_ready'], json_decode(
            (string) $response->getBody(),
            true,
            flags: JSON_THROW_ON_ERROR,
        ));
    }

    /** @param array<string, string> $expectedBody */
    #[DataProvider('productionRoutes')]
    public function testProductionRoutesReturnOnlySafeDeterministicJson(string $path, array $expectedBody): void
    {
        $response = ProductionHttpRuntimeFactory::create()->handle(HttpTestFactory::request('GET', $path));
        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertSame($expectedBody, json_decode($body, true, flags: JSON_THROW_ON_ERROR));

        foreach (['secret', 'php_version', 'filesystem', 'environment', 'debug', 'dependency'] as $unsafe) {
            self::assertStringNotContainsString($unsafe, strtolower($body));
        }
    }

    public function testQueryStringDoesNotChangeRouteSelection(): void
    {
        $runtime = ProductionHttpRuntimeFactory::create();
        $plain = $runtime->handle(HttpTestFactory::request('GET', '/health/live'));
        $queried = $runtime->handle(HttpTestFactory::request('GET', '/health/live?source=test'));

        self::assertSame((string) $plain->getBody(), (string) $queried->getBody());
    }

    public function testForwardedAndMethodOverrideHeadersDoNotAffectRouting(): void
    {
        $request = HttpTestFactory::request('POST', '/health/live')
            ->withHeader('X-Forwarded-Proto', 'https')
            ->withHeader('X-HTTP-Method-Override', 'GET');
        $response = ProductionHttpRuntimeFactory::create()->handle($request);

        self::assertSame(405, $response->getStatusCode());
    }
}
