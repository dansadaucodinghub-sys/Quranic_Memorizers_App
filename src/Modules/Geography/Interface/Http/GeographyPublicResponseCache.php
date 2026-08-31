<?php

declare(strict_types=1);

namespace Qmdb\Modules\Geography\Interface\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class GeographyPublicResponseCache
{
    public function __construct(private ResponseFactoryInterface $responses)
    {
    }

    public function cache(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $datasetChecksum,
        string $selector,
        string $locale,
        bool $fragment = false,
    ): ResponseInterface {
        $etag = '"' . hash('sha256', $datasetChecksum . '|' . $selector . '|' . $locale . '|' . ($fragment ? 'fragment' : 'page')) . '"';
        $headers = [
            'Cache-Control' => 'public, max-age=3600',
            'ETag' => $etag,
            'Vary' => 'Accept, Accept-Language',
            'X-Content-Type-Options' => 'nosniff',
        ];
        $ifNoneMatch = $request->getHeaderLine('If-None-Match');
        if (strlen($ifNoneMatch) <= 2048 && $this->matches($ifNoneMatch, $etag)) {
            $notModified = $this->responses->createResponse(304);
            foreach ($headers as $name => $value) {
                $notModified = $notModified->withHeader($name, $value);
            }

            return $notModified;
        }
        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }

    private function matches(string $header, string $etag): bool
    {
        foreach (array_slice(explode(',', $header), 0, 30) as $candidate) {
            if (trim($candidate) === '*' || hash_equals($etag, trim($candidate))) {
                return true;
            }
        }

        return false;
    }
}
