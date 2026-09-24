<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Interface\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\Community\Application\CommunityCommentService;
use Qmdb\Modules\Community\Application\CommunityInteractionService;
use Qmdb\Modules\Community\Application\CommunityPublicClipService;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Routing\RouteAttributes;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Presentation\View\ViewData;

final readonly class CommunityEngagementController implements Controller
{
    public function __construct(
        private AuthenticatedRequestGuard $authentication,
        private IdentityCsrf $csrf,
        private CommunityCommentService $comments,
        private CommunityInteractionService $interactions,
        private CommunityPublicClipService $clips,
        private IdentityAccessView $views,
        private Psr17Factory $responses,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $parameters = $request->getAttribute(RouteAttributes::PARAMETERS);
        if (!is_array($parameters) || !is_string($parameters['clipId'] ?? null)) {
            return $this->responses->createResponse(404);
        }
        try {
            $clipId = UuidV7::fromString($parameters['clipId']);
        } catch (\InvalidArgumentException) {
            return $this->responses->createResponse(404);
        }
        $actor = $this->authentication->context($request);
        if ($request->getMethod() === 'POST' && $actor === null) {
            return $this->authentication->rejection($request);
        }
        $csrf = $actor === null ? null : $this->csrf->issue($request, CsrfAction::COMMUNITY_ENGAGEMENT);
        $status = 200;
        $error = '';
        if ($request->getMethod() === 'POST' && $actor !== null && $csrf !== null) {
            try {
                $body = $request->getParsedBody();
                if (!is_array($body)) {
                    throw new \InvalidArgumentException('Engagement form is invalid.');
                }
                $token = $body['csrf_token'] ?? null;
                if (
                    !is_string($token) || !$this->csrf->validates(
                        $request,
                        CsrfAction::COMMUNITY_ENGAGEMENT,
                        $csrf['cookie'],
                        $token
                    )
                ) {
                    $status = 403;
                    $error = 'community.engagement.csrf_failed';
                } else {
                    $submission = UuidV7::fromString($this->field($body, 'submission_id', 36));
                    $route = $request->getAttribute(RouteAttributes::NAME);
                    if ($route === 'community.clip.interaction') {
                        $kind = $parameters['kind'] ?? null;
                        if (!is_string($kind) || !in_array($kind, ['reaction', 'bookmark'], true)) {
                            throw new \InvalidArgumentException('Interaction kind is invalid.');
                        }
                        $this->interactions->transition(
                            $actor,
                            $submission,
                            $clipId,
                            strtoupper($kind),
                            $this->field($body, 'action', 8),
                            $this->version($body)
                        );
                        return $this->views->redirect('/clips/' . $clipId->toString() . '?saved=1', $csrf['cookie']);
                    }
                    $commentId = isset($parameters['commentId']) && is_string($parameters['commentId'])
                        ? UuidV7::fromString($parameters['commentId']) : null;
                    match ($route) {
                        'community.clip.comment.create' => $this->comments->create(
                            $actor,
                            $submission,
                            $clipId,
                            null,
                            $this->field($body, 'body', 8000)
                        ),
                        'community.clip.comment.reply' => $this->comments->create(
                            $actor,
                            $submission,
                            $clipId,
                            $commentId ?? throw new \InvalidArgumentException('Comment is missing.'),
                            $this->field($body, 'body', 8000)
                        ),
                        'community.clip.comment.edit' => $this->comments->edit(
                            $actor,
                            $submission,
                            $clipId,
                            $commentId ?? throw new \InvalidArgumentException('Comment is missing.'),
                            $this->version($body),
                            $this->field($body, 'body', 8000)
                        ),
                        'community.clip.comment.remove' => $this->comments->remove(
                            $actor,
                            $submission,
                            $clipId,
                            $commentId ?? throw new \InvalidArgumentException('Comment is missing.'),
                            $this->version($body)
                        ),
                        default => throw new \InvalidArgumentException('Comment route is invalid.'),
                    };
                    return $this->views->redirect('/clips/' . $clipId->toString() . '/comments?saved=1', $csrf['cookie']);
                }
            } catch (\InvalidArgumentException) {
                $status = 422;
                $error = 'community.engagement.invalid';
            } catch (\DomainException) {
                $status = 409;
                $error = 'community.engagement.unavailable';
            }
        }
        $list = $this->comments->visible($clipId, $actor?->accountInternalId);
        $clip = $this->clips->detail($clipId, $actor?->accountInternalId);
        if ($list === null || $clip === null) {
            return $this->responses->createResponse(404)->withHeader('Cache-Control', 'private, no-store');
        }
        foreach ($list as &$comment) {
            $comment['reply_submission'] = UuidV7::generate()->toString();
            $comment['edit_submission'] = UuidV7::generate()->toString();
            $comment['remove_submission'] = UuidV7::generate()->toString();
        }
        unset($comment);
        return $this->views->render(
            $request,
            'pages.community-comments',
            'fragments.community-comments',
            new ViewData([
                'clip_id' => $clipId->toString(), 'comments' => $list,
                'comment_policy' => $clip['comment_policy'],
                'csrf_token' => $csrf['token'] ?? '',
                'submission_id' => UuidV7::generate()->toString(),
                'authenticated' => $actor !== null, 'error' => $error,
                'saved' => $request->getMethod() === 'GET'
                    && ($request->getQueryParams()['saved'] ?? null) === '1',
            ]),
            'community.comments.title',
            $status,
            $csrf['cookie'] ?? null,
            true
        )->withHeader('Cache-Control', 'private, no-store');
    }

    /** @param array<array-key,mixed> $body */
    private function field(array $body, string $name, int $maximum): string
    {
        $value = $body[$name] ?? null;
        if (!is_string($value) || strlen($value) > $maximum) {
            throw new \InvalidArgumentException('Engagement form field is invalid.');
        }
        return trim($value);
    }

    /** @param array<array-key,mixed> $body */
    private function version(array $body): int
    {
        $value = $this->field($body, 'expected_version', 10);
        if (preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/', $value) !== 1) {
            throw new \InvalidArgumentException('Engagement version is invalid.');
        }
        return (int) $value;
    }
}
