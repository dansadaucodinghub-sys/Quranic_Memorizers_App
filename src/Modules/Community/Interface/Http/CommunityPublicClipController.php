<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Interface\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\Community\Application\CommunityPublicClipService;
use Qmdb\Modules\Community\Application\CommunityInteractionService;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Modules\MediaDelivery\Domain\MediaRange;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Routing\RouteAttributes;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Presentation\View\ViewData;

final readonly class CommunityPublicClipController implements Controller
{
    public function __construct(
        private CommunityPublicClipService $clips,
        private AuthenticatedRequestGuard $authentication,
        private IdentityAccessView $views,
        private Psr17Factory $responses,
        private CommunityInteractionService $interactions,
        private IdentityCsrf $csrf,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $parameters = $request->getAttribute(RouteAttributes::PARAMETERS);
        if (!is_array($parameters) || !is_string($parameters['clipId'] ?? null)) {
            return $this->unavailable();
        }
        try {
            $clipId = UuidV7::fromString($parameters['clipId']);
        } catch (\InvalidArgumentException) {
            return $this->unavailable();
        }
        $actor = $this->authentication->context($request);
        $viewerId = $actor?->accountInternalId;
        $route = $request->getAttribute(RouteAttributes::NAME);
        if ($route === 'community.clip.media') {
            return $this->media($request, $clipId, $viewerId);
        }
        if ($route !== 'community.clip.detail') {
            return $this->unavailable();
        }
        $clip = $this->clips->detail($clipId, $viewerId);
        if ($clip === null) {
            return $this->unavailable();
        }
        $csrf = $actor === null ? null : $this->csrf->issue($request, CsrfAction::COMMUNITY_ENGAGEMENT);
        $interactionState = $actor === null ? null : $this->interactions->state($actor, $clipId);
        return $this->views->render(
            $request,
            'pages.community-clip',
            'fragments.community-clip',
            new ViewData([
                'clip' => $clip,
                'authenticated' => $actor !== null,
                'csrf_token' => $csrf['token'] ?? '',
                'interaction_state' => $interactionState ?? [],
                'reaction_submission' => UuidV7::generate()->toString(),
                'bookmark_submission' => UuidV7::generate()->toString(),
                'saved' => ($request->getQueryParams()['saved'] ?? null) === '1',
            ]),
            'community.clip.title',
            200,
            $csrf['cookie'] ?? null
        )->withHeader('Cache-Control', 'private, no-store')
            ->withHeader('Vary', 'Cookie, Accept, X-Requested-With');
    }

    private function media(ServerRequestInterface $request, UuidV7 $clipId, ?int $viewerId): ResponseInterface
    {
        $media = $this->clips->media($clipId, $viewerId);
        if ($media === null) {
            return $this->unavailable();
        }
        $contents = $media['contents'];
        $size = strlen($contents);
        $etag = '"' . bin2hex($media['sha256']) . '"';
        $rangeHeader = $request->getHeaderLine('Range');
        if ($request->hasHeader('If-Range') && $request->getHeaderLine('If-Range') !== $etag) {
            $rangeHeader = '';
        }
        try {
            $range = MediaRange::fromHeader($rangeHeader, $size);
        } catch (\InvalidArgumentException) {
            return $this->responses->createResponse(416)
                ->withHeader('Content-Range', 'bytes */' . $size)
                ->withHeader('Cache-Control', 'private, no-store');
        }
        if ($range === null && trim($request->getHeaderLine('If-None-Match')) === $etag) {
            return $this->responses->createResponse(304)->withHeader('ETag', $etag)
                ->withHeader('Cache-Control', 'private, no-store');
        }
        $body = $range === null ? $contents : substr($contents, $range->start, $range->length());
        $response = $this->responses->createResponse($range === null ? 200 : 206)
            ->withHeader('Content-Type', $media['mime_type'])
            ->withHeader('Content-Disposition', 'inline')
            ->withHeader('Content-Length', (string) strlen($body))
            ->withHeader('Accept-Ranges', 'bytes')
            ->withHeader('ETag', $etag)
            ->withHeader('Cache-Control', 'private, no-store')
            ->withHeader('Vary', 'Cookie')
            ->withHeader('X-Content-Type-Options', 'nosniff');
        if ($range !== null) {
            $response = $response->withHeader(
                'Content-Range',
                'bytes ' . $range->start . '-' . $range->end . '/' . $size
            );
        }
        $response->getBody()->write($body);
        return $response;
    }

    private function unavailable(): ResponseInterface
    {
        return $this->responses->createResponse(404)
            ->withHeader('Cache-Control', 'private, no-store');
    }
}
