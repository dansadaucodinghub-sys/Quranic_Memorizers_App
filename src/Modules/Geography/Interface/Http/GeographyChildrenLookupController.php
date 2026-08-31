<?php

declare(strict_types=1);

namespace Qmdb\Modules\Geography\Interface\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\Geography\Application\AdministrativeAreaChildrenHandler;
use Qmdb\Modules\Geography\Application\AdministrativeAreaChildrenQuery;
use Qmdb\Modules\Geography\Domain\GeographyPublicId;
use Qmdb\Modules\Geography\Domain\Repository\AdministrativeAreaRepository;
use Qmdb\Modules\Geography\Domain\Repository\GeographyDatasetRepository;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Message\ProblemDetails;
use Qmdb\Shared\Http\Message\ProblemDetailsResponseFactory;
use Qmdb\Shared\Presentation\Response\FragmentResponseFactory;
use Qmdb\Shared\Presentation\View\PhpViewRenderer;
use Qmdb\Shared\Presentation\View\PresentationRequestContext;
use Qmdb\Shared\Presentation\View\ViewData;
use Throwable;

final readonly class GeographyChildrenLookupController implements Controller
{
    public function __construct(
        private AdministrativeAreaChildrenHandler $children,
        private AdministrativeAreaRepository $areas,
        private GeographyDatasetRepository $datasets,
        private PresentationRequestContext $context,
        private PhpViewRenderer $views,
        private FragmentResponseFactory $fragments,
        private ProblemDetailsResponseFactory $problems,
        private GeographyPublicResponseCache $cache,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $parent = $request->getQueryParams()['parent'] ?? null;
        try {
            $parentId = new GeographyPublicId(is_string($parent) ? $parent : '');
        } catch (Throwable) {
            return $this->problems->createForRequest(ProblemDetails::badRequest(), $request);
        }
        $area = $this->areas->findActiveByPublicId($parentId->value());
        $dataset = $this->datasets->findActiveForCountry('NG');
        if ($area === null || $dataset === null || $area['administrative_level'] !== 1) {
            return $this->problems->createForRequest(ProblemDetails::notFound(), $request);
        }
        $context = $this->context->fromRequest($request);
        $fragment = $this->views->render(
            'fragments.geography-child-select',
            new ViewData(['parent' => $area, 'children' => $this->children->handle(new AdministrativeAreaChildrenQuery($parentId))]),
            $context['translator'],
        );
        $response = $this->fragments->create($fragment);

        return $this->cache->cache(
            $request,
            $response,
            $dataset['content_sha256'],
            'geography-children|' . $parentId->value(),
            $context['translator']->locale()->value(),
            true,
        );
    }
}
