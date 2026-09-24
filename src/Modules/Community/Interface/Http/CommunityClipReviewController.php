<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Interface\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\Community\Application\RecitationClipService;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Modules\TenancyContext\Application\Exception\TenantContextRequiredException;
use Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Routing\RouteAttributes;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Presentation\View\ViewData;

final readonly class CommunityClipReviewController implements Controller
{
    public function __construct(
        private AuthenticatedRequestGuard $authentication,
        private TenantContextRequiredGuard $tenancy,
        private IdentityCsrf $csrf,
        private RecitationClipService $clips,
        private IdentityAccessView $views,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = $this->authentication->context($request);
        if ($actor === null) {
            return $this->authentication->rejection($request);
        }
        try {
            $tenant = $this->tenancy->require($request);
        } catch (TenantContextRequiredException) {
            return $this->views->redirect('/account/workspaces?context_required=1');
        }
        $csrf = $this->csrf->issue($request, CsrfAction::COMMUNITY_CLIP_REVIEW);
        $status = 200;
        $error = '';
        if ($request->getMethod() === 'POST') {
            try {
                $body = $request->getParsedBody();
                $parameters = $request->getAttribute(RouteAttributes::PARAMETERS);
                if (
                    !is_array($body) || !is_array($parameters)
                    || !is_string($parameters['clipId'] ?? null)
                ) {
                    throw new \InvalidArgumentException('Clip review form is invalid.');
                }
                $token = $body['csrf_token'] ?? null;
                if (
                    !is_string($token) || !$this->csrf->validates(
                        $request,
                        CsrfAction::COMMUNITY_CLIP_REVIEW,
                        $csrf['cookie'],
                        $token
                    )
                ) {
                    $status = 403;
                    $error = 'community.review.csrf_failed';
                } else {
                    $submission = $body['submission_id'] ?? null;
                    $version = $body['expected_version'] ?? null;
                    if (
                        !is_string($submission) || !is_string($version)
                        || preg_match('/\A[1-9][0-9]{0,9}\z/', $version) !== 1
                    ) {
                        throw new \InvalidArgumentException('Clip review version is invalid.');
                    }
                    $this->clips->publish(
                        $actor,
                        $tenant,
                        UuidV7::fromString($submission),
                        UuidV7::fromString($parameters['clipId']),
                        (int) $version
                    );
                    return $this->views->redirect('/workspace/community/review?published=1', $csrf['cookie']);
                }
            } catch (\InvalidArgumentException) {
                $status = 422;
                $error = 'community.review.invalid';
            } catch (\DomainException) {
                $status = 409;
                $error = 'community.review.unavailable';
            }
        }
        try {
            $queue = $this->clips->reviewQueue($actor, $tenant);
        } catch (\DomainException) {
            $queue = [];
            $status = 403;
            $error = 'community.review.unavailable';
        }
        foreach ($queue as &$item) {
            $item['submission_id'] = UuidV7::generate()->toString();
        }
        unset($item);
        return $this->views->render(
            $request,
            'pages.community-clip-review',
            'fragments.community-clip-review',
            new ViewData([
                'queue' => $queue, 'csrf_token' => $csrf['token'], 'error' => $error,
                'published' => $request->getMethod() === 'GET'
                    && ($request->getQueryParams()['published'] ?? null) === '1',
            ]),
            'community.review.title',
            $status,
            $csrf['cookie'],
            true
        );
    }
}
