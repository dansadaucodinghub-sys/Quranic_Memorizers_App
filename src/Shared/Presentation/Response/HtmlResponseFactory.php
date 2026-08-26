<?php

declare(strict_types=1);

namespace Qmdb\Shared\Presentation\Response;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Qmdb\Shared\Presentation\Html\SafeHtml;

final readonly class HtmlResponseFactory
{
    public function __construct(
        private ResponseFactoryInterface $responses,
        private StreamFactoryInterface $streams,
    ) {
    }

    public function create(SafeHtml $html, int $status = 200): ResponseInterface
    {
        return $this->responses->createResponse($status)
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withBody($this->streams->createStream($html->trustedHtml()));
    }
}
