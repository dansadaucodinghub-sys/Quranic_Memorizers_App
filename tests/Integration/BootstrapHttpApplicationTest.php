<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Qmdb\Bootstrap\Http\BootstrapHttpApplication;
use Qmdb\Bootstrap\Http\BootstrapHttpResponse;
use Qmdb\Tests\Support\ApplicationTestFactory;
use ReflectionClass;

final class BootstrapHttpApplicationTest extends TestCase
{
    public function testSuccessfulResponseUsesHttpStatus200(): void
    {
        self::assertSame(200, $this->successfulResponse()->statusCode());
    }

    public function testSuccessfulResponseUsesRequiredHeaders(): void
    {
        $headers = $this->successfulResponse()->headers();

        self::assertSame('application/json; charset=utf-8', $headers['Content-Type']);
        self::assertSame('no-store', $headers['Cache-Control']);
        self::assertSame('nosniff', $headers['X-Content-Type-Options']);
    }

    public function testSuccessfulBodyIsDeterministicJsonWithSafeFields(): void
    {
        $response = $this->successfulResponse();

        self::assertSame(
            '{"application":"QMDB","status":"ready","phase":"P1",'
            . '"batch":"QMDB-P1-B02","baseline":"QMDB-P0-FRZ-001"}',
            $response->body(),
        );
        self::assertSame(
            [
                'application' => 'QMDB',
                'status' => 'ready',
                'phase' => 'P1',
                'batch' => 'QMDB-P1-B02',
                'baseline' => 'QMDB-P0-FRZ-001',
            ],
            json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR),
        );
    }

    public function testSuccessfulBodyDoesNotExposeRuntimeOrHostDetails(): void
    {
        $body = $this->successfulResponse()->body();

        foreach (['8.5.4', PHP_OS, __DIR__, 'password', 'secret', 'token'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $body);
        }
    }

    public function testRuntimeFailureReturnsHttpStatus500(): void
    {
        $response = $this->http()->handle('8.2.0', ['json']);

        self::assertSame(500, $response->statusCode());
    }

    public function testRuntimeFailureUsesGenericPublicContent(): void
    {
        $response = $this->http()->handle('8.2.0', []);

        self::assertSame(
            '{"application":"QMDB","status":"error","code":"BOOTSTRAP_FAILURE"}',
            $response->body(),
        );
    }

    public function testRuntimeFailureDoesNotExposeViolationDetails(): void
    {
        $response = $this->http()->handle('1.0.0-sensitive-value', []);
        $body = $response->body();

        self::assertStringNotContainsString('sensitive-value', $body);
        self::assertStringNotContainsString('PHP_VERSION_TOO_LOW', $body);
        self::assertStringNotContainsString('MISSING_EXTENSION', $body);
        self::assertStringNotContainsString(__DIR__, $body);
        self::assertStringNotContainsString('trace', strtolower($body));
    }

    public function testFailureResponseStillUsesRequiredHeaders(): void
    {
        $headers = $this->http()->handle('8.2.0', [])->headers();

        self::assertSame('application/json; charset=utf-8', $headers['Content-Type']);
        self::assertSame('no-store', $headers['Cache-Control']);
        self::assertSame('nosniff', $headers['X-Content-Type-Options']);
    }

    public function testResponseObjectIsImmutable(): void
    {
        $reflection = new ReflectionClass(BootstrapHttpResponse::class);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
    }

    public function testResponseDoesNotEmitHeadersOrBodyDirectly(): void
    {
        ob_start();
        $response = $this->successfulResponse();
        $emitted = ob_get_clean();

        self::assertSame('', $emitted);
        self::assertSame(200, $response->statusCode());
    }

    public function testSuccessIsIndependentOfExtensionInputOrder(): void
    {
        $first = $this->http()->handle('8.5.4', ['json', 'mbstring']);
        $second = $this->http()->handle('8.5.4', ['mbstring', 'json']);

        self::assertSame($first->statusCode(), $second->statusCode());
        self::assertSame($first->headers(), $second->headers());
        self::assertSame($first->body(), $second->body());
    }

    public function testPublicSuccessDoesNotExposeConfigurationDetails(): void
    {
        $body = $this->successfulResponse()->body();

        foreach (['environment', 'debug', 'timezone', 'configuration', 'source'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, strtolower($body));
        }
    }

    public function testConfigurationFailureResponseIsGenericAndSafe(): void
    {
        $response = BootstrapHttpResponse::configurationFailure();

        self::assertSame(500, $response->statusCode());
        self::assertSame(
            '{"application":"QMDB","status":"error","code":"CONFIGURATION_FAILURE"}',
            $response->body(),
        );
        self::assertStringNotContainsString('APP_ENV', $response->body());
        self::assertStringNotContainsString('secret', strtolower($response->body()));
        self::assertSame('no-store', $response->headers()['Cache-Control']);
        self::assertSame('nosniff', $response->headers()['X-Content-Type-Options']);
    }

    private function successfulResponse(): BootstrapHttpResponse
    {
        return $this->http()->handle('8.5.4', ['json', 'mbstring']);
    }

    private function http(): BootstrapHttpApplication
    {
        return new BootstrapHttpApplication(ApplicationTestFactory::create());
    }
}
