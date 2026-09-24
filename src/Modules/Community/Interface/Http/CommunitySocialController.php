<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Interface\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\Community\Application\CommunitySocialService;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Routing\RouteAttributes;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Presentation\View\ViewData;

final readonly class CommunitySocialController implements Controller
{
    public function __construct(
        private AuthenticatedRequestGuard $authentication,
        private IdentityCsrf $csrf,
        private CommunitySocialService $social,
        private IdentityAccessView $views,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = $this->authentication->context($request);
        if ($actor === null) {
            return $this->authentication->rejection($request);
        }
        $csrf = $this->csrf->issue($request, CsrfAction::COMMUNITY_SOCIAL);
        $status = 200;
        $error = '';
        if ($request->getMethod() === 'POST') {
            try {
                $body = $request->getParsedBody();
                $parameters = $request->getAttribute(RouteAttributes::PARAMETERS);
                if (
                    !is_array($body) || !is_array($parameters)
                    || !is_string($parameters['profileId'] ?? null)
                    || !is_string($parameters['action'] ?? null)
                ) {
                    throw new \InvalidArgumentException('Social action form is invalid.');
                }
                $token = $body['csrf_token'] ?? null;
                if (
                    !is_string($token) || !$this->csrf->validates(
                        $request,
                        CsrfAction::COMMUNITY_SOCIAL,
                        $csrf['cookie'],
                        $token
                    )
                ) {
                    $status = 403;
                    $error = 'community.social.csrf_failed';
                } else {
                    $submission = $body['submission_id'] ?? null;
                    $version = $body['expected_version'] ?? null;
                    if (
                        !is_string($submission) || !is_string($version)
                        || preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/', $version) !== 1
                    ) {
                        throw new \InvalidArgumentException('Social action version is invalid.');
                    }
                    $action = strtoupper($parameters['action']);
                    if (
                        !in_array($action, ['FOLLOW', 'UNFOLLOW', 'ACCEPT', 'DECLINE',
                        'REVOKE_FOLLOWER', 'BLOCK', 'UNBLOCK', 'MUTE', 'UNMUTE'], true)
                    ) {
                        throw new \InvalidArgumentException('Social action is invalid.');
                    }
                    $profileId = UuidV7::fromString($parameters['profileId']);
                    $this->social->transition(
                        $actor,
                        UuidV7::fromString($submission),
                        $profileId,
                        $action,
                        (int) $version
                    );
                    $path = in_array($action, ['BLOCK', 'UNBLOCK', 'UNMUTE'], true)
                        ? '/account/community/safety?saved=1'
                        : (in_array($action, ['ACCEPT', 'DECLINE', 'REVOKE_FOLLOWER'], true)
                            ? '/account/community/followers?saved=1'
                            : ($action === 'UNFOLLOW' ? '/account/community/following?saved=1'
                                : '/community/profiles/' . $profileId->toString() . '?saved=1'));
                    return $this->views->redirect($path, $csrf['cookie']);
                }
            } catch (\InvalidArgumentException) {
                $status = 422;
                $error = 'community.social.invalid';
            } catch (\DomainException) {
                $status = 409;
                $error = 'community.social.unavailable';
            }
        }
        $routeParameters = $request->getAttribute(RouteAttributes::PARAMETERS);
        $followersPage = $request->getAttribute(RouteAttributes::NAME) === 'account.community.followers'
            || (is_array($routeParameters) && is_string($routeParameters['action'] ?? null)
                && in_array(
                    strtoupper($routeParameters['action']),
                    ['ACCEPT', 'DECLINE', 'REVOKE_FOLLOWER'],
                    true
                ));
        $followingPage = $request->getAttribute(RouteAttributes::NAME) === 'account.community.following'
            || (is_array($routeParameters) && is_string($routeParameters['action'] ?? null)
                && strtoupper($routeParameters['action']) === 'UNFOLLOW');
        $list = $followersPage ? $this->social->incoming($actor)
            : ($followingPage ? $this->social->outgoing($actor) : $this->social->safetyList($actor));
        foreach ($list as &$item) {
            $item['block_submission'] = UuidV7::generate()->toString();
            $item['mute_submission'] = UuidV7::generate()->toString();
            $item['follow_submission'] = UuidV7::generate()->toString();
            $item['follow_submissions'] = [
                'accept' => UuidV7::generate()->toString(),
                'decline' => UuidV7::generate()->toString(),
                'revoke_follower' => UuidV7::generate()->toString(),
            ];
        }
        unset($item);
        return $this->views->render(
            $request,
            $followersPage ? 'pages.community-followers'
                : ($followingPage ? 'pages.community-following' : 'pages.community-safety'),
            $followersPage ? 'fragments.community-followers'
                : ($followingPage ? 'fragments.community-following' : 'fragments.community-safety'),
            new ViewData([
                'relationships' => $list, 'csrf_token' => $csrf['token'], 'error' => $error,
                'saved' => $request->getMethod() === 'GET'
                    && ($request->getQueryParams()['saved'] ?? null) === '1',
            ]),
            $followersPage ? 'community.social.followers_title'
                : ($followingPage ? 'community.social.following_title' : 'community.social.safety_title'),
            $status,
            $csrf['cookie'],
            true
        );
    }
}
