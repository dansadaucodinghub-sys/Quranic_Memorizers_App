<?php

declare(strict_types=1);

namespace Qmdb\Modules\Geography\Interface\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\Geography\Domain\Repository\AdministrativeAreaRepository;
use Qmdb\Modules\Geography\Domain\Repository\GeographyDatasetRepository;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Message\ProblemDetails;
use Qmdb\Shared\Http\Message\ProblemDetailsResponseFactory;
use Qmdb\Shared\Http\Routing\RouteAttributes;
use Qmdb\Shared\Presentation\View\PageRenderer;
use Qmdb\Shared\Presentation\View\PresentationRequestContext;
use Qmdb\Shared\Presentation\View\ViewData;

final readonly class NigeriaGeographyAreaController implements Controller
{
    public function __construct(
        private AdministrativeAreaRepository $areas,
        private GeographyDatasetRepository $datasets,
        private PresentationRequestContext $context,
        private PageRenderer $pages,
        private ProblemDetailsResponseFactory $problems,
        private GeographyPublicResponseCache $cache,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $parameters = $request->getAttribute(RouteAttributes::PARAMETERS);
        $slug = is_array($parameters) && is_string($parameters['levelOneSlug'] ?? null) ? $parameters['levelOneSlug'] : '';
        $area = $this->areas->findActiveLevelOneBySlug('NG', $slug);
        $dataset = $this->datasets->findActiveForCountry('NG');
        if ($area === null || $dataset === null) {
            return $this->problems->createForRequest(ProblemDetails::notFound(), $request);
        }
        $children = $this->areas->listActiveChildren($area['public_id']);
        $context = $this->context->fromRequest($request);
        $page = $this->pages->render(
            'pages.nigeria-administrative-area',
            new ViewData(['area' => $area, 'children' => $children]),
            $context['translator'],
            $context['nonce'],
            'title.geography.area',
            $context['path'],
        );
        $factory = new Psr17Factory();
        $response = $factory->createResponse(200)->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withBody($factory->createStream($page->trustedHtml()));

        return $this->cache->cache(
            $request,
            $response,
            $dataset['content_sha256'],
            'nigeria-area|' . $slug,
            $context['translator']->locale()->value(),
        );
    }
}
