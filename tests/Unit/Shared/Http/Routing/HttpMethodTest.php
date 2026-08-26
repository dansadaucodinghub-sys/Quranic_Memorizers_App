<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Http\Routing;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Http\Routing\HttpMethod;
use Qmdb\Shared\Http\Routing\RouteCollectionException;

final class HttpMethodTest extends TestCase
{
    /** @return iterable<string, array{string, HttpMethod}> */
    public static function supportedMethods(): iterable
    {
        foreach (HttpMethod::cases() as $method) {
            yield $method->value => [$method->value, $method];
        }
    }

    #[DataProvider('supportedMethods')]
    public function testSupportedMethodsParseCanonically(string $input, HttpMethod $expected): void
    {
        self::assertSame($expected, HttpMethod::parse($input));
        self::assertSame($input, $expected->value);
    }

    #[DataProvider('invalidMethods')]
    public function testUnsupportedOrNonCanonicalMethodsFail(string $input): void
    {
        $this->expectException(RouteCollectionException::class);
        HttpMethod::parse($input);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidMethods(): iterable
    {
        yield 'empty' => [''];
        yield 'lowercase' => ['get'];
        yield 'trace' => ['TRACE'];
        yield 'connect' => ['CONNECT'];
        yield 'whitespace' => [' GET '];
    }

    public function testMethodSortingIsUniqueAndDeterministic(): void
    {
        self::assertSame(
            [HttpMethod::GET, HttpMethod::HEAD, HttpMethod::DELETE, HttpMethod::OPTIONS],
            HttpMethod::sort([
                HttpMethod::OPTIONS,
                HttpMethod::DELETE,
                HttpMethod::GET,
                HttpMethod::HEAD,
                HttpMethod::GET,
            ]),
        );
    }
}
