<?php

declare(strict_types=1);

namespace Qmdb\Shared\Localization;

use Psr\Http\Message\ServerRequestInterface;

final readonly class LocaleResolver
{
    private const MAX_HEADER_LENGTH = 1024;
    private const MAX_ITEMS = 20;

    public function __construct(private SupportedLocales $supportedLocales)
    {
    }

    public function resolve(ServerRequestInterface $request): Locale
    {
        $queryValue = $request->getQueryParams()['lang'] ?? null;
        if (is_string($queryValue)) {
            return $this->supportedLocales->get($queryValue) ?? $this->supportedLocales->default();
        }

        $asynchronous = $request->getHeaderLine('X-QMDB-Locale');
        if ($asynchronous !== '') {
            return $this->supportedLocales->get($asynchronous) ?? $this->supportedLocales->default();
        }

        return $this->fromAcceptLanguage($request->getHeaderLine('Accept-Language'));
    }

    public function fromAcceptLanguage(string $header): Locale
    {
        if ($header === '' || strlen($header) > self::MAX_HEADER_LENGTH) {
            return $this->supportedLocales->default();
        }

        $matches = [];
        foreach (array_slice(explode(',', $header), 0, self::MAX_ITEMS) as $position => $item) {
            $parts = array_map('trim', explode(';', $item));
            if (preg_match('/^([A-Za-z]{2,8})(?:-[A-Za-z0-9]{1,8})*$/D', $parts[0], $tag) !== 1) {
                continue;
            }
            $quality = 1.0;
            if (isset($parts[1])) {
                if (preg_match('/^q=(0(?:\.\d{1,3})?|1(?:\.0{1,3})?)$/D', $parts[1], $q) !== 1) {
                    continue;
                }
                $quality = (float) $q[1];
            }
            $primary = strtolower($tag[1]);
            if ($quality > 0.0 && $this->supportedLocales->supports($primary)) {
                $matches[] = ['locale' => $primary, 'quality' => $quality, 'position' => $position];
            }
        }
        usort($matches, static fn (array $left, array $right): int =>
            $right['quality'] <=> $left['quality'] ?: $left['position'] <=> $right['position']);

        return $matches === []
            ? $this->supportedLocales->default()
            : ($this->supportedLocales->get($matches[0]['locale']) ?? $this->supportedLocales->default());
    }
}
