<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Presentation\Response\PageOrFragmentResponseFactory;
use Qmdb\Shared\Presentation\View\PageRenderer;
use Qmdb\Shared\Presentation\View\PhpViewRenderer;
use Qmdb\Shared\Presentation\View\PresentationRequestContext;
use Qmdb\Shared\Presentation\View\ViewData;

final readonly class SystemAboutPageController implements Controller
{
    public function __construct(
        private SystemPresentationDataProvider $data,
        private PresentationRequestContext $context,
        private PhpViewRenderer $views,
        private PageRenderer $pages,
        private PageOrFragmentResponseFactory $responses,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $context = $this->context->fromRequest($request);
        $view = new ViewData($this->data->information());
        $page = $this->pages->render(
            'pages.system-about',
            $view,
            $context['translator'],
            $context['nonce'],
            'title.about',
            $context['path'],
            $context['tenant'],
            $context['authenticated'],
        );
        $fragment = $this->views->render('fragments.system-about-dialog', $view, $context['translator']);

        return $this->responses->create($request, $page, $fragment);
    }
}
