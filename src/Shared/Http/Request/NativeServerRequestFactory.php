<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Request;

use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;

final readonly class NativeServerRequestFactory
{
    public function __construct(private ServerRequestCreator $creator)
    {
    }

    public function createFromGlobals(): ServerRequestInterface
    {
        $server = $_SERVER;
        foreach (array_keys($server) as $name) {
            if (str_starts_with((string) $name, 'HTTP_X_FORWARDED_')) {
                unset($server[$name]);
            }
        }

        if (!isset($server['REQUEST_METHOD'])) {
            $server['REQUEST_METHOD'] = 'GET';
        }

        $headers = ServerRequestCreator::getHeadersFromServer($server);
        $body = fopen('php://input', 'r');

        return $this->creator->fromArrays(
            server: $server,
            headers: $headers,
            cookie: $_COOKIE,
            get: $_GET,
            post: $_POST === [] ? null : $_POST,
            files: $_FILES,
            body: $body === false ? null : $body,
        );
    }

    /**
     * @param array<string, mixed> $server
     * @param array<string, string|list<string>> $headers
     * @param array<string, mixed> $cookies
     * @param array<string, mixed> $query
     * @param array<string, mixed>|null $parsedBody
     * @param array<string, mixed> $files
     * @param resource|string|StreamInterface|null $body
     */
    public function createFromArrays(
        array $server,
        array $headers = [],
        array $cookies = [],
        array $query = [],
        ?array $parsedBody = null,
        array $files = [],
        mixed $body = null,
    ): ServerRequestInterface {
        foreach (array_keys($server) as $name) {
            if (str_starts_with($name, 'HTTP_X_FORWARDED_')) {
                unset($server[$name]);
            }
        }

        foreach (array_keys($headers) as $name) {
            if (str_starts_with(strtolower($name), 'x-forwarded-')) {
                unset($headers[$name]);
            }
        }

        return $this->creator->fromArrays(
            $server,
            $headers,
            $cookies,
            $query,
            $parsedBody,
            $files,
            $body,
        );
    }
}
