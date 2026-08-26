<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Shared\Http\Message\JsonResponseFactory;
use Qmdb\Shared\Http\Message\ProblemDetailsResponseFactory;

final class HttpTestFactory
{
    private function __construct()
    {
    }

    public static function psr17(): Psr17Factory
    {
        return new Psr17Factory();
    }

    public static function json(): JsonResponseFactory
    {
        $factory = self::psr17();

        return new JsonResponseFactory($factory, $factory);
    }

    public static function problems(): ProblemDetailsResponseFactory
    {
        return new ProblemDetailsResponseFactory(self::json());
    }

    public static function request(string $method = 'GET', string $target = '/'): ServerRequestInterface
    {
        return (new ServerRequest($method, $target))->withRequestTarget($target);
    }
}
