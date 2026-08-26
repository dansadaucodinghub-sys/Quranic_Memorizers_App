<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Configuration;

use InvalidArgumentException;

final readonly class PublicApplicationBaseUrl
{
    private string $value;

    public function __construct(string $value, bool $requireHttps)
    {
        if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw new InvalidArgumentException('Public application URL is invalid.');
        }
        $parts = parse_url($value);
        if (!is_array($parts)) {
            throw new InvalidArgumentException('Public application URL is invalid.');
        }
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true) || ($requireHttps && $scheme !== 'https')) {
            throw new InvalidArgumentException('Public application URL scheme is invalid.');
        }
        if (
            !isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            throw new InvalidArgumentException('Public application URL authority is invalid.');
        }
        $path = (string)($parts['path'] ?? '');
        if ($path !== '' && $path !== '/') {
            throw new InvalidArgumentException('Public application URL path is invalid.');
        }
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $this->value = $scheme . '://' . strtolower((string)$parts['host']) . $port;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function origin(): string
    {
        return $this->value;
    }

    public function path(string $path): string
    {
        if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//')) {
            throw new InvalidArgumentException('Public application path is invalid.');
        }

        return $this->value . $path;
    }
}
