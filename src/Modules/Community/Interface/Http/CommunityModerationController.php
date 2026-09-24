<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Interface\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\Community\Application\CommunityModerationService;
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

final readonly class CommunityModerationController implements Controller
{
    public function __construct(
        private AuthenticatedRequestGuard $authentication,
        private TenantContextRequiredGuard $tenancy,
        private IdentityCsrf $csrf,
        private CommunityModerationService $moderation,
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
        $csrf = $this->csrf->issue($request, CsrfAction::COMMUNITY_MODERATION);
        $error = '';
        $status = 200;
        if ($request->getMethod() === 'POST') {
            try {
                $body = $request->getParsedBody();
                $params = $request->getAttribute(RouteAttributes::PARAMETERS);
                $routeName = $request->getAttribute(RouteAttributes::NAME);
                $resourceName = $routeName === 'workspace.community.moderation.comment.decide'
                    ? 'commentId' : 'caseId';
                $resourceValue = is_array($params) ? ($params[$resourceName] ?? null) : null;
                if (
                    !is_array($body) || !is_array($params)
                    || !is_string($resourceValue)
                ) {
                    throw new \InvalidArgumentException('Moderation form is invalid.');
                }
                $token = $body['csrf_token'] ?? null;
                if (
                    !is_string($token) || !$this->csrf->validates(
                        $request,
                        CsrfAction::COMMUNITY_MODERATION,
                        $csrf['cookie'],
                        $token
                    )
                ) {
                    $status = 403;
                    $error = 'community.moderation.csrf_failed';
                } else {
                    $submission = $body['submission_id'] ?? null;
                    $version = $body['expected_version'] ?? null;
                    if (
                        !is_string($submission) || !is_string($version)
                        || preg_match('/\A[1-9][0-9]{0,9}\z/', $version) !== 1
                    ) {
                        throw new \InvalidArgumentException('Moderation version is invalid.');
                    }
                    $submissionId = UuidV7::fromString($submission);
                    $expected = (int) $version;
                    if ($routeName === 'workspace.community.moderation.comment.decide') {
                        $this->moderation->decideComment(
                            $actor,
                            $tenant,
                            $submissionId,
                            UuidV7::fromString($resourceValue),
                            $expected,
                            $this->field($body, 'action', 16)
                        );
                        return $this->views->redirect('/workspace/community/moderation?saved=1', $csrf['cookie']);
                    }
                    $caseId = UuidV7::fromString($resourceValue);
                    match ($routeName) {
                        'workspace.community.moderation.assign' => $this->moderation->assignSelf(
                            $actor,
                            $tenant, $submissionId, $caseId, $expected
                        ),
                        'workspace.community.moderation.start' => $this->moderation->startReview(
                            $actor,
                            $tenant, $submissionId, $caseId, $expected
                        ),
                        'workspace.community.moderation.decide' => $this->moderation->decide(
                            $actor,
                            $tenant, $submissionId, $caseId, $expected,
                            $this->field($body, 'action', 32), $this->field($body, 'reason_code', 48)
                        ),
                        default => throw new \InvalidArgumentException('Unknown moderation action.'),
                    };
                    return $this->views->redirect('/workspace/community/moderation?saved=1', $csrf['cookie']);
                }
            } catch (\InvalidArgumentException) {
                $status = 422;
                $error = 'community.moderation.invalid';
            } catch (\DomainException) {
                $status = 409;
                $error = 'community.moderation.unavailable';
            }
        }
        $queue = $this->moderation->queue($actor, $tenant);
        $caseId = '';
        $reports = [];
        if ($request->getMethod() === 'GET') {
            $parameters = $request->getAttribute(RouteAttributes::PARAMETERS);
            if (is_array($parameters) && is_string($parameters['caseId'] ?? null)) {
                try {
                    $caseId = UuidV7::fromString($parameters['caseId'])->toString();
                    if (!in_array($caseId, array_column($queue, 'public_id'), true)) {
                        $status = 404;
                        $error = 'community.moderation.unavailable';
                        $caseId = '';
                    } else {
                        $reports = $this->moderation->caseReports($actor, $tenant, UuidV7::fromString($caseId));
                    }
                } catch (\InvalidArgumentException) {
                    $status = 404;
                    $error = 'community.moderation.unavailable';
                    $caseId = '';
                }
            }
        }
        foreach ($queue as &$item) {
            $item['submission_id'] = UuidV7::generate()->toString();
        }
        unset($item);
        $heldComments = $this->moderation->heldComments($actor, $tenant);
        foreach ($heldComments as &$comment) {
            $comment['submission_id'] = UuidV7::generate()->toString();
        }
        unset($comment);
        return $this->views->render(
            $request,
            'pages.community-moderation',
            'fragments.community-moderation',
            new ViewData([
                'queue' => $queue, 'csrf_token' => $csrf['token'], 'error' => $error,
                'case_id' => $caseId, 'reports' => $reports,
                'held_comments' => $heldComments,
                'saved' => $request->getMethod() === 'GET'
                    && ($request->getQueryParams()['saved'] ?? null) === '1',
            ]),
            'community.moderation.title',
            $status,
            $csrf['cookie'],
            true
        );
    }

    /** @param array<array-key,mixed> $body */
    private function field(array $body, string $name, int $maximum): string
    {
        $value = $body[$name] ?? null;
        if (!is_string($value) || strlen($value) > $maximum) {
            throw new \InvalidArgumentException('Moderation form field is invalid.');
        }
        return trim($value);
    }
}
