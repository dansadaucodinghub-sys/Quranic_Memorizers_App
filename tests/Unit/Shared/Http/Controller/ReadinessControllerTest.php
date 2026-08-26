<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Http\Controller;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Database\Health\DatabaseHealthCheck;
use Qmdb\Shared\Database\Health\DatabaseHealthReport;
use Qmdb\Shared\Database\Health\DatabaseHealthStatus;
use Qmdb\Shared\Http\Controller\ReadinessController;
use Qmdb\Shared\Http\Message\JsonResponseFactory;

final class ReadinessControllerTest extends TestCase
{
    /** @return iterable<string, array{DatabaseHealthStatus, int, string}> */
    public static function statuses(): iterable
    {
        yield 'ready' => [DatabaseHealthStatus::READY, 200, 'ready'];
        yield 'unavailable' => [DatabaseHealthStatus::UNAVAILABLE, 503, 'not_ready'];
        yield 'invalid session' => [DatabaseHealthStatus::INVALID_SESSION, 503, 'not_ready'];
        yield 'tls required' => [DatabaseHealthStatus::TLS_REQUIRED, 503, 'not_ready'];
    }

    #[DataProvider('statuses')]
    public function testReadinessMapsInternalHealthToMinimalPublicResponse(
        DatabaseHealthStatus $status,
        int $expectedCode,
        string $expectedStatus,
    ): void {
        $check = new class ($status) implements DatabaseHealthCheck {
            public function __construct(private readonly DatabaseHealthStatus $status)
            {
            }

            public function check(): DatabaseHealthReport
            {
                return new DatabaseHealthReport($this->status);
            }
        };
        $factory = new Psr17Factory();
        $controller = new ReadinessController(new JsonResponseFactory($factory, $factory), $check);

        $response = $controller->handle(new ServerRequest('GET', '/health/ready'));

        self::assertSame($expectedCode, $response->getStatusCode());
        self::assertSame(
            ['status' => $expectedStatus],
            json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR),
        );
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
    }
}
