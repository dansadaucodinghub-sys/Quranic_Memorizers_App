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
            $selector = $this->selector($request);
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
            new ViewData([
                'parent' => $area,
                'children' => $this->children->handle(new AdministrativeAreaChildrenQuery($parentId)),
                'child_field_name' => $selector['field_name'],
                'child_input_id' => $selector['input_id'],
                'child_region_id' => $selector['region_id'],
                'selected_public_id' => $selector['selected_public_id'],
            ]),
            $context['translator'],
        );
        $response = $this->fragments->create($fragment);

        return $this->cache->cache(
            $request,
            $response,
            $dataset['content_sha256'],
            'geography-children|' . $parentId->value() . '|' . $selector['field_name'] . '|' . $selector['selected_public_id'],
            $context['translator']->locale()->value(),
            true,
        );
    }

    /** @return array{field_name:string,input_id:string,region_id:string,selected_public_id:string} */
    private function selector(ServerRequestInterface $request): array
    {
        $query = $request->getQueryParams();
        $field = $query['field'] ?? '';
        $selected = $query['selected'] ?? '';
        if (!is_string($field) || !is_string($selected) || strlen($selected) > 64) {
            throw new \InvalidArgumentException('Geography child selector is invalid.');
        }

        return match ($field) {
            'origin_level_two_area_public_id' => [
                'field_name' => $field,
                'input_id' => 'origin_level_two_area_public_id',
                'region_id' => 'origin-level-two-region',
                'selected_public_id' => $selected,
            ],
            'residence_level_two_area_public_id' => [
                'field_name' => $field,
                'input_id' => 'residence_level_two_area_public_id',
                'region_id' => 'residence-level-two-region',
                'selected_public_id' => $selected,
            ],
            '' => [
                'field_name' => 'geography_level_two',
                'input_id' => 'geography-level-two',
                'region_id' => 'geography-level-two-region',
                'selected_public_id' => '',
            ],
            default => throw new \InvalidArgumentException('Geography child selector is invalid.'),
        };
    }
}
