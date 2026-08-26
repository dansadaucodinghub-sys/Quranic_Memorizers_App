<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Routing;

enum HttpMethod: string
{
    case GET = 'GET';
    case HEAD = 'HEAD';
    case POST = 'POST';
    case PUT = 'PUT';
    case PATCH = 'PATCH';
    case DELETE = 'DELETE';
    case OPTIONS = 'OPTIONS';

    public static function parse(string $method): self
    {
        return self::tryParse($method)
            ?? throw new RouteCollectionException('Unsupported HTTP method.');
    }

    public static function tryParse(string $method): ?self
    {
        if ($method === '' || strtoupper($method) !== $method) {
            return null;
        }

        return self::tryFrom($method);
    }

    /**
     * @param iterable<self> $methods
     * @return list<self>
     */
    public static function sort(iterable $methods): array
    {
        $unique = [];
        foreach ($methods as $method) {
            $unique[$method->value] = $method;
        }

        $ordered = [];
        foreach (self::cases() as $candidate) {
            if (isset($unique[$candidate->value])) {
                $ordered[] = $candidate;
            }
        }

        return $ordered;
    }
}
