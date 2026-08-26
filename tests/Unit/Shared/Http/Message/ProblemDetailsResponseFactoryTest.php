<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Http\Message;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Http\Message\ProblemDetails;
use Qmdb\Tests\Support\Http\HttpTestFactory;

final class ProblemDetailsResponseFactoryTest extends TestCase
{
    /** @return iterable<string, array{ProblemDetails, int, string, string}> */
    public static function problems(): iterable
    {
        yield 'bad request' => [ProblemDetails::badRequest(), 400, 'Bad Request', 'REQUEST_TARGET_INVALID'];
        yield 'not found' => [ProblemDetails::notFound(), 404, 'Not Found', 'ROUTE_NOT_FOUND'];
        yield 'method' => [ProblemDetails::methodNotAllowed(), 405, 'Method Not Allowed', 'METHOD_NOT_ALLOWED'];
        yield 'server' => [
            ProblemDetails::internalServerError(),
            500,
            'Internal Server Error',
            'INTERNAL_SERVER_ERROR',
        ];
    }

    #[DataProvider('problems')]
    public function testProblemResponsesHaveStableShape(
        ProblemDetails $problem,
        int $status,
        string $title,
        string $code,
    ): void {
        $response = HttpTestFactory::problems()->create($problem);

        self::assertSame($status, $response->getStatusCode());
        self::assertSame('application/problem+json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertSame(
            sprintf(
                '{"type":"about:blank","title":"%s","status":%d,"code":"%s"}',
                $title,
                $status,
                $code,
            ),
            (string) $response->getBody(),
        );
    }

    public function testMethodNotAllowedIncludesOnlySuppliedAllowMetadata(): void
    {
        $response = HttpTestFactory::problems()->create(
            ProblemDetails::methodNotAllowed(),
            ['Allow' => 'GET, HEAD, OPTIONS'],
        );
        $body = (string) $response->getBody();

        self::assertSame('GET, HEAD, OPTIONS', $response->getHeaderLine('Allow'));
        foreach (['Exception', 'Stack trace', __FILE__, 'secret-test-value'] as $unsafe) {
            self::assertStringNotContainsString($unsafe, $body);
        }
    }
}
