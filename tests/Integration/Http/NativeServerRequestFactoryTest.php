<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UploadedFileInterface;
use Qmdb\Shared\Http\Request\NativeServerRequestFactory;

final class NativeServerRequestFactoryTest extends TestCase
{
    public function testExplicitArraysCreateDeterministicPsr7Request(): void
    {
        $request = $this->factory()->createFromArrays(
            server: [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/records?filter=active',
                'QUERY_STRING' => 'filter=active',
                'REQUEST_SCHEME' => 'http',
                'HTTP_HOST' => 'example.test',
                'SERVER_PROTOCOL' => 'HTTP/1.1',
            ],
            headers: ['X-Test' => 'safe'],
            cookies: ['session' => 'opaque'],
            query: ['filter' => 'active'],
            parsedBody: ['name' => 'record'],
            body: 'name=record',
        );

        self::assertSame('POST', $request->getMethod());
        self::assertSame('/records', $request->getUri()->getPath());
        self::assertSame(['filter' => 'active'], $request->getQueryParams());
        self::assertSame(['session' => 'opaque'], $request->getCookieParams());
        self::assertSame(['name' => 'record'], $request->getParsedBody());
        self::assertSame('safe', $request->getHeaderLine('X-Test'));
        self::assertSame('name=record', (string) $request->getBody());
    }

    public function testForwardedHeadersAreRemovedInsteadOfTrusted(): void
    {
        $request = $this->factory()->createFromArrays(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/health/live',
                'REQUEST_SCHEME' => 'http',
                'HTTP_HOST' => 'example.test',
                'HTTP_X_FORWARDED_PROTO' => 'https',
            ],
            headers: [
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Host' => 'attacker.test',
            ],
        );

        self::assertSame('http', $request->getUri()->getScheme());
        self::assertSame('example.test', $request->getUri()->getHost());
        self::assertFalse($request->hasHeader('X-Forwarded-Proto'));
        self::assertFalse($request->hasHeader('X-Forwarded-Host'));
    }

    public function testUploadedFileIsNormalizedByTrustedImplementation(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'qmdb-upload-');
        self::assertIsString($path);
        self::assertNotFalse(file_put_contents($path, 'payload'));

        try {
            $request = $this->factory()->createFromArrays(
                server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/upload'],
                files: [
                    'document' => [
                        'tmp_name' => $path,
                        'size' => 7,
                        'error' => UPLOAD_ERR_OK,
                        'name' => 'document.txt',
                        'type' => 'text/plain',
                    ],
                ],
            );
            $uploaded = $request->getUploadedFiles()['document'] ?? null;

            if (!$uploaded instanceof UploadedFileInterface) {
                self::fail('The uploaded document was not normalized to a PSR-7 uploaded file.');
            }
            self::assertSame('document.txt', $uploaded->getClientFilename());
            self::assertSame('text/plain', $uploaded->getClientMediaType());
            self::assertSame(7, $uploaded->getSize());
            $uploaded->getStream()->close();
            unset($uploaded, $request);
        } finally {
            unlink($path);
        }
    }

    private function factory(): NativeServerRequestFactory
    {
        $psr17 = new Psr17Factory();

        return new NativeServerRequestFactory(new ServerRequestCreator(
            $psr17,
            $psr17,
            $psr17,
            $psr17,
        ));
    }
}
