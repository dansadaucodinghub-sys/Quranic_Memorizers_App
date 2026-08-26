<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Http\Message;

use InvalidArgumentException;
use JsonException;
use PHPUnit\Framework\TestCase;
use Qmdb\Tests\Support\Http\HttpTestFactory;

final class JsonResponseFactoryTest extends TestCase
{
    public function testCreatesImmutableSafeJsonResponse(): void
    {
        $response = HttpTestFactory::json()->create(
            ['name' => 'حفص', 'url' => 'https://example.test/a/b'],
            201,
            ['X-Test' => 'safe'],
        );

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertSame('safe', $response->getHeaderLine('X-Test'));
        self::assertSame('{"name":"حفص","url":"https://example.test/a/b"}', (string) $response->getBody());

        $modified = $response->withStatus(202);
        self::assertSame(201, $response->getStatusCode());
        self::assertSame(202, $modified->getStatusCode());
    }

    public function testInvalidJsonFailsAtInternalBoundary(): void
    {
        $this->expectException(JsonException::class);
        HttpTestFactory::json()->create(['invalid' => "\xB1\x31"]);
    }

    public function testInvalidStatusFails(): void
    {
        $this->expectException(InvalidArgumentException::class);
        HttpTestFactory::json()->create([], 99);
    }

    public function testRequiredHeadersCannotBeOverriddenCaseInsensitively(): void
    {
        $this->expectException(InvalidArgumentException::class);
        HttpTestFactory::json()->create([], headers: ['content-type' => 'text/html']);
    }

    public function testHeaderInjectionFailsBeforeResponseConstruction(): void
    {
        $this->expectException(InvalidArgumentException::class);
        HttpTestFactory::json()->create([], headers: ['X-Test' => "safe\r\nInjected: yes"]);
    }
}
