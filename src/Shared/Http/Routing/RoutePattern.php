<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Routing;

final readonly class RoutePattern
{
    /** @var list<string> */
    private array $parameterNames;
    private string $compiledPattern;
    private int $staticSegmentCount;
    private int $staticCharacterCount;

    public function __construct(private string $value)
    {
        if ($value === '' || $value[0] !== '/') {
            throw new RouteCollectionException('Route pattern must begin with a slash.');
        }

        if (
            str_contains($value, '?')
            || str_contains($value, '#')
            || str_contains($value, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $value) === 1
            || ($value !== '/' && str_ends_with($value, '/'))
            || str_contains($value, '//')
        ) {
            throw new RouteCollectionException('Route pattern contains unsupported syntax.');
        }

        if (!mb_check_encoding($value, 'UTF-8')) {
            throw new RouteCollectionException('Route pattern must be valid UTF-8.');
        }

        if ($value === '/') {
            $this->parameterNames = [];
            $this->compiledPattern = '~^/$~uD';
            $this->staticSegmentCount = 0;
            $this->staticCharacterCount = 1;

            return;
        }

        $parameterNames = [];
        $compiledSegments = [];
        $staticSegmentCount = 0;
        $staticCharacterCount = 0;

        foreach (explode('/', substr($value, 1)) as $segment) {
            if ($segment === '.' || $segment === '..' || $segment === '') {
                throw new RouteCollectionException('Route pattern contains an invalid segment.');
            }

            if (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)\}$/D', $segment, $matches) === 1) {
                $name = $matches[1];
                if (in_array($name, $parameterNames, true)) {
                    throw new RouteCollectionException('Route parameter names must be unique.');
                }

                $parameterNames[] = $name;
                $compiledSegments[] = sprintf('(?P<%s>[^/]+)', $name);
                continue;
            }

            if (str_contains($segment, '{') || str_contains($segment, '}') || str_contains($segment, '*')) {
                throw new RouteCollectionException('Route pattern parameter syntax is invalid.');
            }

            $compiledSegments[] = preg_quote($segment, '~');
            ++$staticSegmentCount;
            $staticCharacterCount += mb_strlen($segment, 'UTF-8');
        }

        $this->parameterNames = $parameterNames;
        $this->compiledPattern = '~^/' . implode('/', $compiledSegments) . '$~uD';
        $this->staticSegmentCount = $staticSegmentCount;
        $this->staticCharacterCount = $staticCharacterCount;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isStatic(): bool
    {
        return $this->parameterNames === [];
    }

    public function staticSegmentCount(): int
    {
        return $this->staticSegmentCount;
    }

    public function staticCharacterCount(): int
    {
        return $this->staticCharacterCount;
    }

    /** @return list<RouteParameter>|null */
    public function match(string $decodedPath): ?array
    {
        $matched = preg_match($this->compiledPattern, $decodedPath, $matches);
        if ($matched !== 1) {
            return null;
        }

        $parameters = [];
        foreach ($this->parameterNames as $name) {
            $value = $matches[$name] ?? null;
            if (!is_string($value)) {
                return null;
            }

            $parameters[] = new RouteParameter($name, $value);
        }

        return $parameters;
    }

    public function overlaps(self $other): bool
    {
        $left = $this->segmentsForComparison();
        $right = $other->segmentsForComparison();
        if (count($left) !== count($right)) {
            return false;
        }

        foreach ($left as $index => $segment) {
            $otherSegment = $right[$index];
            if ($segment !== null && $otherSegment !== null && $segment !== $otherSegment) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string|null> */
    private function segmentsForComparison(): array
    {
        if ($this->value === '/') {
            return [];
        }

        return array_map(
            static fn (string $segment): ?string => str_starts_with($segment, '{') ? null : $segment,
            explode('/', substr($this->value, 1)),
        );
    }
}
