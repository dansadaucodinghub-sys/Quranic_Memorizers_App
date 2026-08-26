<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Http\Routing;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Http\Routing\RouteCollectionException;
use Qmdb\Shared\Http\Routing\RoutePattern;

final class RoutePatternTest extends TestCase
{
    #[DataProvider('validPatterns')]
    public function testValidPatternsCompileAndMatch(string $pattern, string $path): void
    {
        $routePattern = new RoutePattern($pattern);

        self::assertNotNull($routePattern->match($path));
        self::assertSame($pattern, $routePattern->value());
    }

    /** @return iterable<string, array{string, string}> */
    public static function validPatterns(): iterable
    {
        yield 'root' => ['/', '/'];
        yield 'static' => ['/health/live', '/health/live'];
        yield 'parameter' => ['/records/{recordId}', '/records/abc123'];
        yield 'unicode static' => ['/سجل', '/سجل'];
    }

    #[DataProvider('invalidPatterns')]
    public function testUnsupportedPatternsFailAtConstruction(string $pattern): void
    {
        $this->expectException(RouteCollectionException::class);
        new RoutePattern($pattern);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidPatterns(): iterable
    {
        yield 'missing slash' => ['records/{id}'];
        yield 'query' => ['/records?x=1'];
        yield 'fragment' => ['/records#x'];
        yield 'trailing slash' => ['/records/'];
        yield 'duplicate slash' => ['/records//one'];
        yield 'duplicate parameter' => ['/{id}/{id}'];
        yield 'numeric parameter' => ['/records/{1id}'];
        yield 'optional syntax' => ['/records/{id?}'];
        yield 'wildcard' => ['/records/*'];
        yield 'custom regex' => ['/records/{id:[0-9]+}'];
        yield 'backslash' => ['/records\\one'];
        yield 'control' => ["/records/\x01"];
        yield 'dot segment' => ['/records/../one'];
    }

    public function testStaticTextCannotInjectTheCompiledRegularExpression(): void
    {
        $pattern = new RoutePattern('/records/a.+(b)');

        self::assertNotNull($pattern->match('/records/a.+(b)'));
        self::assertNull($pattern->match('/records/axxxb'));
    }

    public function testParameterMatchesOneSegmentOnly(): void
    {
        $pattern = new RoutePattern('/records/{recordId}');

        self::assertNull($pattern->match('/records/'));
        self::assertNull($pattern->match('/records/a/b'));
        $parameters = $pattern->match('/records/abc');
        self::assertNotNull($parameters);
        self::assertSame('abc', $parameters[0]->value());
    }
}
