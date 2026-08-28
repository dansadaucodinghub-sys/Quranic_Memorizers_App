<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Http\Middleware;

use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Shared\Http\Middleware\ExceptionHandlingMiddleware;
use Qmdb\Shared\Http\Request\RequestContextAttributes;
use Qmdb\Shared\Observability\Correlation\CorrelationId;
use Qmdb\Modules\SecurityAuthorization\Application\Exception\AuthorizationDeniedException;
use Qmdb\Tests\Support\Http\CallableRequestHandler;
use Qmdb\Tests\Support\Http\HttpTestFactory;
use Qmdb\Tests\Support\Observability\RecordingThrowableReporter;
use RuntimeException;

final class ExceptionHandlingMiddlewareTest extends TestCase
{
    public function testUnexpectedThrowableBecomesGenericProblemResponse(): void
    {
        $secret = 'QMDB_EXCEPTION_SECRET_217b';
        $middleware = new ExceptionHandlingMiddleware(
            HttpTestFactory::problems(),
            new RecordingThrowableReporter(),
        );
        $response = $middleware->process(
            HttpTestFactory::request(),
            new CallableRequestHandler(
                static function (ServerRequestInterface $request) use ($secret): never {
                    throw new RuntimeException($secret . ' at ' . __FILE__);
                },
            ),
        );
        $body = (string) $response->getBody();

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('application/problem+json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame(
            '{"type":"about:blank","title":"Internal Server Error","status":500,"code":"INTERNAL_SERVER_ERROR"}',
            $body,
        );
        self::assertStringNotContainsString($secret, $body);
        self::assertStringNotContainsString(__FILE__, $body);
        self::assertStringNotContainsString('Stack trace', $body);
    }

    public function testNormalRoutingResponseIsPreserved(): void
    {
        $expected = new Response(404);
        $response = (new ExceptionHandlingMiddleware(
            HttpTestFactory::problems(),
            new RecordingThrowableReporter(),
        ))->process(
            HttpTestFactory::request(),
            new CallableRequestHandler(
                static fn (ServerRequestInterface $request): Response => $expected,
            ),
        );

        self::assertSame($expected, $response);
    }

    public function testAuthorizationDenialBecomesGenericCorrelated403WithoutErrorReporting(): void
    {
        $reporter = new RecordingThrowableReporter();
        $requestId = '8c3a9e84f2294c11a2681369cf313daf';
        $response = (new ExceptionHandlingMiddleware(
            HttpTestFactory::problems(),
            $reporter,
        ))->process(
            HttpTestFactory::request()->withAttribute(
                RequestContextAttributes::REQUEST_ID,
                new CorrelationId($requestId),
            ),
            new CallableRequestHandler(
                static function (ServerRequestInterface $request): never {
                    throw new AuthorizationDeniedException();
                },
            ),
        );
        $body = (string) $response->getBody();

        self::assertSame(403, $response->getStatusCode());
        self::assertSame(
            '{"type":"about:blank","title":"Forbidden","status":403,'
                . '"code":"AUTHORIZATION_DENIED","request_id":"' . $requestId . '"}',
            $body,
        );
        self::assertSame([], $reporter->reports());
        foreach (['permission', 'role', 'workspace', 'scope', 'stack', 'not permitted'] as $sensitive) {
            self::assertStringNotContainsString($sensitive, strtolower($body));
        }
    }
}
