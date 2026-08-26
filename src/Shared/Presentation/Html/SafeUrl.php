<?php

declare(strict_types=1);

namespace Qmdb\Shared\Presentation\Html;

use InvalidArgumentException;

final readonly class SafeUrl
{
    private function __construct(private string $value)
    {
    }

    public static function applicationRelative(string $path): self
    {
        if (
            $path === ''
            || $path[0] !== '/'
            || str_starts_with($path, '//')
            || str_contains($path, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $path) === 1
            || parse_url($path, PHP_URL_HOST) !== null
        ) {
            throw new InvalidArgumentException('Application URL is unsafe.');
        }

        return new self($path);
    }

    /** @param array<string, string> $parameters */
    public function withQuery(array $parameters): self
    {
        $path = explode('?', $this->value, 2)[0];
        $query = http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);

        return new self($query === '' ? $path : $path . '?' . $query);
    }

    public function value(): string
    {
        return $this->value;
    }
}
