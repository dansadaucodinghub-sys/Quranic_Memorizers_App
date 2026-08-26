<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Http\Routing;

use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Http\Routing\HttpMethod;
use Qmdb\Shared\Http\Routing\Route;
use Qmdb\Shared\Http\Routing\RouteCollection;
use Qmdb\Shared\Http\Routing\RouteCollectionException;
use Qmdb\Shared\Http\Routing\RoutePattern;
use Qmdb\Tests\Support\Http\CallableController;

final class RouteCollectionTest extends TestCase
{
    public function testCollectionPreservesOrderAndCountWithoutExposingMutableStorage(): void
    {
        $first = $this->route('records.index', HttpMethod::GET, '/records');
        $second = $this->route('records.store', HttpMethod::POST, '/records');
        $collection = new RouteCollection($first, $second);

        self::assertCount(2, $collection);
        self::assertSame([$first, $second], iterator_to_array($collection));
    }

    public function testDuplicateRouteNameFails(): void
    {
        $this->expectException(RouteCollectionException::class);
        new RouteCollection(
            $this->route('records.index', HttpMethod::GET, '/records'),
            $this->route('records.index', HttpMethod::POST, '/records'),
        );
    }

    public function testDuplicateMethodAndPatternFails(): void
    {
        $this->expectException(RouteCollectionException::class);
        new RouteCollection(
            $this->route('records.index', HttpMethod::GET, '/records'),
            $this->route('records.list', HttpMethod::GET, '/records'),
        );
    }

    public function testSamePathWithDifferentMethodsSucceeds(): void
    {
        $collection = new RouteCollection(
            $this->route('records.index', HttpMethod::GET, '/records'),
            $this->route('records.store', HttpMethod::POST, '/records'),
        );

        self::assertCount(2, $collection);
    }

    public function testAmbiguousParameterizedRoutesFailDuringConstruction(): void
    {
        $this->expectException(RouteCollectionException::class);
        new RouteCollection(
            $this->route('records.show', HttpMethod::GET, '/records/{recordId}'),
            $this->route('records.named', HttpMethod::GET, '/records/{name}'),
        );
    }

    public function testDistinctSpecificityMayOverlapDeterministically(): void
    {
        $collection = new RouteCollection(
            $this->route('records.scoped', HttpMethod::GET, '/records/{scope}/{id}'),
            $this->route('records.active', HttpMethod::GET, '/records/active/{id}'),
        );

        self::assertCount(2, $collection);
    }

    public function testUnsafeRouteNamesFail(): void
    {
        $this->expectException(RouteCollectionException::class);
        $this->route(' Records.Index ', HttpMethod::GET, '/records');
    }

    public function testEmptyMethodsFail(): void
    {
        $this->expectException(RouteCollectionException::class);
        new Route(
            'records.index',
            [],
            new RoutePattern('/records'),
            new CallableController(static fn (): Response => new Response()),
        );
    }

    private function route(string $name, HttpMethod $method, string $pattern): Route
    {
        return new Route(
            $name,
            [$method],
            new RoutePattern($pattern),
            new CallableController(static fn (): Response => new Response()),
        );
    }
}
