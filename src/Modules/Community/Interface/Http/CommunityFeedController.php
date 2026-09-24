<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Interface\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\Community\Application\CommunityFeedService;
use Qmdb\Modules\Community\Application\CommunitySocialService;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Routing\RouteAttributes;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Presentation\View\ViewData;

final readonly class CommunityFeedController implements Controller
{
    public function __construct(
        private CommunityFeedService $feed,
        private AuthenticatedRequestGuard $authentication,
        private IdentityAccessView $views,
        private CommunitySocialService $social,
        private IdentityCsrf $csrf,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $following = $request->getAttribute(RouteAttributes::NAME) === 'account.community.feed';
        $profileRoute = in_array($request->getAttribute(RouteAttributes::NAME), [
            'community.profile.detail', 'community.profile.clips',
        ], true);
        $profileId = null;
        if ($profileRoute) {
            $parameters = $request->getAttribute(RouteAttributes::PARAMETERS);
            if (is_array($parameters) && is_string($parameters['profileId'] ?? null)) {
                try {
                    $profileId = UuidV7::fromString($parameters['profileId']);
                } catch (\InvalidArgumentException) {
                    $profileId = null;
                }
            }
        }
        $actor = $this->authentication->context($request);
        if ($following && $actor === null) {
            return $this->authentication->rejection($request);
        }
        $query = $request->getQueryParams();
        $cursor = $query['cursor'] ?? null;
        $language = $query['language'] ?? null;
        $surah = $query['surah'] ?? null;
        $items = [];
        $next = null;
        $profileAlias = null;
        $error = '';
        $status = $profileRoute && $profileId === null ? 404 : 200;
        try {
            if (
                ($cursor !== null && !is_string($cursor))
                || ($language !== null && !is_string($language))
                || ($surah !== null && (!is_string($surah) || ($surah !== '' && !ctype_digit($surah))))
            ) {
                throw new \InvalidArgumentException('Feed filter is invalid.');
            }
            $page = $profileRoute && $profileId === null ? ['items' => [], 'next_cursor' => null, 'profile_alias' => null]
                : $this->feed->page(
                    $actor?->accountInternalId,
                    $cursor,
                    $language === '' ? null : $language,
                    $surah === null || $surah === '' ? null : (int) $surah,
                    $following,
                    $profileId,
                );
            $items = $page['items'];
            $next = $page['next_cursor'];
            $profileAlias = $page['profile_alias'];
            if ($profileRoute && $profileAlias === null) {
                $status = 404;
            }
        } catch (\InvalidArgumentException) {
            $status = 422;
            $error = 'community.feed.invalid';
        }
        $socialState = [];
        $socialCsrf = null;
        if ($actor !== null && $profileRoute && $profileId !== null && $profileAlias !== null) {
            $socialState = $this->social->state($actor, $profileId);
            $socialCsrf = $this->csrf->issue($request, CsrfAction::COMMUNITY_SOCIAL);
        }
        $response = $this->views->render(
            $request,
            'pages.community-feed',
            'fragments.community-feed',
            new ViewData([
                'items' => $items,
                'next_cursor' => $next ?? '',
                'language' => is_string($language) ? $language : '',
                'surah' => is_string($surah) ? $surah : '',
                'error' => $error,
                'title_key' => $following ? 'community.feed.following_title'
                    : ($profileRoute ? 'community.feed.profile_title' : 'community.feed.title'),
                'profile_alias' => $profileAlias ?? '',
                'profile_id' => $profileId?->toString() ?? '',
                'social_state' => $socialState,
                'social_csrf_token' => $socialCsrf['token'] ?? '',
                'social_submission_ids' => [
                    'follow' => UuidV7::generate()->toString(),
                    'block' => UuidV7::generate()->toString(),
                    'mute' => UuidV7::generate()->toString(),
                ],
                'social_saved' => ($query['saved'] ?? null) === '1',
                'base_path' => $following ? '/account/community/feed'
                    : ($profileId === null ? '/community' : '/community/profiles/' . $profileId->toString()
                        . ($request->getAttribute(RouteAttributes::NAME) === 'community.profile.clips' ? '/clips' : '')),
            ]),
            $following ? 'community.feed.following_title'
                : ($profileRoute ? 'community.feed.profile_title' : 'community.feed.title'),
            $status,
            $socialCsrf['cookie'] ?? null,
        );
        return $response->withHeader('Cache-Control', 'private, no-store')
            ->withHeader('Vary', 'Cookie, Accept, X-Requested-With');
    }
}
