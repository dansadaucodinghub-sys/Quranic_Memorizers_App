<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Http\Response;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Http\Response\ResponseEmissionException;
use Qmdb\Shared\Http\Response\SapiResponseEmitter;

final class SapiResponseEmitterTest extends TestCase
{
    public function testPlanPreservesStatusAndMultipleHeaderValues(): void
    {
        $response = (new Response(202))
            ->withHeader('Set-Cookie', ['one=1', 'two=2'])
            ->withHeader('X-Test', 'safe');
        $plan = $this->emitter()->plan($response, 'GET');

        self::assertSame(202, $plan->statusCode());
        self::assertSame([
            ['line' => 'Set-Cookie: one=1', 'replace' => true],
            ['line' => 'Set-Cookie: two=2', 'replace' => false],
            ['line' => 'X-Test: safe', 'replace' => true],
        ], $plan->headers());
        self::assertTrue($plan->shouldEmitBody());
    }

    public function testPlanDropsPhpVersionDisclosureHeader(): void
    {
        $response = (new Response(200))
            ->withHeader('X-Powered-By', 'PHP/8.5.0')
            ->withHeader('X-Safe', 'present');

        self::assertSame(
            [['line' => 'X-Safe: present', 'replace' => true]],
            $this->emitter()->plan($response, 'GET')->headers(),
        );
    }

    #[DataProvider('bodylessResponses')]
    public function testPlanSuppressesBody(string $method, int $status): void
    {
        self::assertFalse($this->emitter()->plan(new Response($status), $method)->shouldEmitBody());
    }

    /** @return iterable<string, array{string, int}> */
    public static function bodylessResponses(): iterable
    {
        yield 'head' => ['HEAD', 200];
        yield 'informational' => ['GET', 101];
        yield 'no content' => ['GET', 204];
        yield 'not modified' => ['GET', 304];
    }

    public function testEmissionUsesBoundedChunksAndRestoresSeekableCursor(): void
    {
        $statuses = [];
        $headers = [];
        $chunks = [];
        $stream = Stream::create('abcdefghij');
        $stream->seek(4);
        $response = new Response(200, ['X-Test' => 'safe'], $stream);
        $emitter = new SapiResponseEmitter(
            headersSent: static fn (): bool => false,
            emitStatus: static function (int $status) use (&$statuses): void {
                $statuses[] = $status;
            },
            emitHeader: static function (string $line, bool $replace) use (&$headers): void {
                $headers[] = [$line, $replace];
            },
            emitOutput: static function (string $chunk) use (&$chunks): void {
                $chunks[] = $chunk;
            },
            chunkSize: 3,
        );

        $emitter->emit($response, 'GET');

        self::assertSame([200], $statuses);
        self::assertSame([['X-Test: safe', true]], $headers);
        self::assertSame(['abc', 'def', 'ghi', 'j'], $chunks);
        self::assertSame(4, $stream->tell());
        self::assertSame('abcdefghij', (string) $stream);
    }

    public function testHeadEmissionProducesNoBodyChunks(): void
    {
        $chunks = [];
        $emitter = new SapiResponseEmitter(
            headersSent: static fn (): bool => false,
            emitStatus: static function (int $status): void {
            },
            emitHeader: static function (string $line, bool $replace): void {
            },
            emitOutput: static function (string $chunk) use (&$chunks): void {
                $chunks[] = $chunk;
            },
        );

        $emitter->emit(new Response(200, [], 'secret body'), 'HEAD');

        self::assertSame([], $chunks);
    }

    public function testAlreadySentHeadersFailThroughDedicatedException(): void
    {
        $this->expectException(ResponseEmissionException::class);

        (new SapiResponseEmitter(headersSent: static fn (): bool => true))->emit(new Response(), 'GET');
    }

    private function emitter(): SapiResponseEmitter
    {
        return new SapiResponseEmitter(headersSent: static fn (): bool => false);
    }
}
