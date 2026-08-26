<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\Http;

use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Http\Response\SapiResponseEmitter;
use Qmdb\Tests\Support\Http\HttpTestFactory;
use Qmdb\Tests\Support\Http\ProductionHttpRuntimeFactory;

final class SapiResponseEmitterTest extends TestCase
{
    public function testKernelResponseEmitsStatusHeadersAndBody(): void
    {
        $status = null;
        $headers = [];
        $output = '';
        $emitter = new SapiResponseEmitter(
            headersSent: static fn (): bool => false,
            emitStatus: static function (int $value) use (&$status): void {
                $status = $value;
            },
            emitHeader: static function (string $line, bool $replace) use (&$headers): void {
                $headers[] = [$line, $replace];
            },
            emitOutput: static function (string $chunk) use (&$output): void {
                $output .= $chunk;
            },
            chunkSize: 4,
        );
        $response = ProductionHttpRuntimeFactory::create()->handle(
            HttpTestFactory::request('GET', '/health/live'),
        );

        $emitter->emit($response, 'GET');

        self::assertSame(200, $status);
        self::assertContains(['Content-Type: application/json; charset=utf-8', true], $headers);
        self::assertContains(['Cache-Control: no-store', true], $headers);
        self::assertSame('{"status":"alive"}', $output);
    }

    public function testHeadKernelResponseEmitsNoBody(): void
    {
        $output = '';
        $emitter = new SapiResponseEmitter(
            headersSent: static fn (): bool => false,
            emitStatus: static function (int $status): void {
            },
            emitHeader: static function (string $line, bool $replace): void {
            },
            emitOutput: static function (string $chunk) use (&$output): void {
                $output .= $chunk;
            },
        );
        $response = ProductionHttpRuntimeFactory::create()->handle(
            HttpTestFactory::request('HEAD', '/health/live'),
        );

        $emitter->emit($response, 'HEAD');

        self::assertSame('', $output);
    }
}
