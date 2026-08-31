<?php

declare(strict_types=1);

namespace Qmdb\Modules\Geography\Interface\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\Geography\Application\NigeriaGeographyDirectoryHandler;
use Qmdb\Modules\Geography\Application\NigeriaGeographyDirectoryQuery;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Presentation\View\PageRenderer;
use Qmdb\Shared\Presentation\View\PresentationRequestContext;
use Qmdb\Shared\Presentation\View\ViewData;

final readonly class NigeriaGeographyDirectoryController implements Controller
{
    public function __construct(
        private NigeriaGeographyDirectoryHandler $directory,
        private PresentationRequestContext $context,
        private PageRenderer $pages,
        private GeographyPublicResponseCache $cache,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $search = $params['q'] ?? '';
        if (!is_string($search)) {
            $search = '';
        }
        $data = $this->directory->handle(new NigeriaGeographyDirectoryQuery(trim($search)));
        $context = $this->context->fromRequest($request);
        $page = $this->pages->render(
            'pages.nigeria-geography-directory',
            new ViewData([...$data, 'search_query' => trim($search)]),
            $context['translator'],
            $context['nonce'],
            'title.geography.nigeria',
            $context['path'],
        );
        $response = (new \Nyholm\Psr7\Factory\Psr17Factory())->createResponse(200)
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withBody((new \Nyholm\Psr7\Factory\Psr17Factory())->createStream($page->trustedHtml()));

        return $this->cache->cache(
            $request,
            $response,
            (string) $data['dataset']['content_sha256'],
            'nigeria-directory|' . trim($search),
            $context['translator']->locale()->value(),
        );
    }
}
