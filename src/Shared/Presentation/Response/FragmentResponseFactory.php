<?php

declare(strict_types=1);

namespace Qmdb\Shared\Presentation\Response;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Qmdb\Shared\Presentation\Html\SafeHtml;
use RuntimeException;

final readonly class FragmentResponseFactory
{
    public function __construct(
        private ResponseFactoryInterface $responses,
        private StreamFactoryInterface $streams,
    ) {
    }

    public function create(SafeHtml $html): ResponseInterface
    {
        $markup = trim($html->trustedHtml());
        if (
            substr_count($markup, 'data-qmdb-fragment-root') !== 1
            || preg_match('/<(?:html|head|body|script|style|iframe|object|embed|link)\b/i', $markup) === 1
            || preg_match('/\son[a-z]+\s*=/i', $markup) === 1
        ) {
            throw new RuntimeException('Rendered fragment violates the fragment contract.');
        }

        return $this->responses->createResponse()
            ->withHeader('Content-Type', FragmentRequestDetector::MEDIA_TYPE . '; charset=utf-8')
            ->withHeader('X-QMDB-Fragment', '1')
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Vary', 'Accept')
            ->withBody($this->streams->createStream($markup));
    }
}
