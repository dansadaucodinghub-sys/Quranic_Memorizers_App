<?php

declare(strict_types=1);

namespace Qmdb\Shared\Presentation\Response;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Shared\Presentation\Html\SafeHtml;

final readonly class PageOrFragmentResponseFactory
{
    public function __construct(
        private FragmentRequestDetector $detector,
        private HtmlResponseFactory $pages,
        private FragmentResponseFactory $fragments,
    ) {
    }

    public function create(ServerRequestInterface $request, SafeHtml $page, SafeHtml $fragment): ResponseInterface
    {
        $response = $this->detector->isFragment($request)
            ? $this->fragments->create($fragment)
            : $this->pages->create($page);

        return $response->withHeader('Vary', 'Accept');
    }
}
