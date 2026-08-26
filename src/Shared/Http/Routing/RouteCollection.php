<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Routing;

use Countable;
use IteratorAggregate;
use Traversable;

/** @implements IteratorAggregate<int, Route> */
final readonly class RouteCollection implements Countable, IteratorAggregate
{
    /** @var list<Route> */
    private array $routes;

    public function __construct(Route ...$routes)
    {
        $names = [];
        $methodPatterns = [];

        foreach ($routes as $route) {
            if (isset($names[$route->name()])) {
                throw new RouteCollectionException('Route names must be unique.');
            }
            $names[$route->name()] = true;

            foreach ($route->methods() as $method) {
                $key = $method->value . ' ' . $route->pattern()->value();
                if (isset($methodPatterns[$key])) {
                    throw new RouteCollectionException('Method and route-pattern pairs must be unique.');
                }
                $methodPatterns[$key] = true;
            }
        }

        $routeCount = count($routes);
        for ($leftIndex = 0; $leftIndex < $routeCount; ++$leftIndex) {
            for ($rightIndex = $leftIndex + 1; $rightIndex < $routeCount; ++$rightIndex) {
                $this->assertNotAmbiguous($routes[$leftIndex], $routes[$rightIndex]);
            }
        }

        $this->routes = array_values($routes);
    }

    public function count(): int
    {
        return count($this->routes);
    }

    /** @return Traversable<int, Route> */
    public function getIterator(): Traversable
    {
        yield from $this->routes;
    }

    private function assertNotAmbiguous(Route $left, Route $right): void
    {
        $sharedMethod = false;
        foreach ($left->methods() as $method) {
            if ($right->allows($method)) {
                $sharedMethod = true;
                break;
            }
        }

        if (!$sharedMethod || !$left->pattern()->overlaps($right->pattern())) {
            return;
        }

        $sameSpecificity = $left->pattern()->staticSegmentCount()
                === $right->pattern()->staticSegmentCount()
            && $left->pattern()->staticCharacterCount()
                === $right->pattern()->staticCharacterCount();

        if ($sameSpecificity && $left->pattern()->value() !== $right->pattern()->value()) {
            throw new RouteCollectionException('Ambiguous route patterns are not permitted.');
        }
    }
}
