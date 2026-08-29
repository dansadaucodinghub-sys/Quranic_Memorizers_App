<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Presentation\Response\HtmlResponseFactory;
use Qmdb\Shared\Presentation\View\PageRenderer;
use Qmdb\Shared\Presentation\View\PresentationRequestContext;
use Qmdb\Shared\Presentation\View\ViewData;

final readonly class SystemHomeController implements Controller
{
    public function __construct(
        private SystemPresentationDataProvider $data,
        private PresentationRequestContext $context,
        private PageRenderer $pages,
        private HtmlResponseFactory $responses,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $context = $this->context->fromRequest($request);
        $information = $this->data->information();
        $html = $this->pages->render(
            'pages.home',
            new ViewData([
            'phase' => $information['phase'],
            'batch' => $information['batch'],
            'status' => $this->data->status(),
            ]),
            $context['translator'],
            $context['nonce'],
            'title.home',
            $context['path'],
            $context['tenant'],
            $context['authenticated']
        );

        return $this->responses->create($html);
    }
}
