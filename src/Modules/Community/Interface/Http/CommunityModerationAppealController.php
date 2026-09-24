<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Interface\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\Community\Application\CommunityModerationAppealService;
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

final readonly class CommunityModerationAppealController implements Controller
{
    public function __construct(
        private AuthenticatedRequestGuard $authentication,
        private TenantContextRequiredGuard $tenancy,
        private IdentityCsrf $csrf,
        private CommunityModerationAppealService $appeals,
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
        $name = $request->getAttribute(RouteAttributes::NAME);
        $reviewer = is_string($name) && str_starts_with($name, 'workspace.community.appeals.');
        $csrf = $this->csrf->issue($request, CsrfAction::COMMUNITY_APPEAL);
        $error = '';
        $status = 200;
        if ($request->getMethod() === 'POST') {
            try {
                $body = $request->getParsedBody();
                $params = $request->getAttribute(RouteAttributes::PARAMETERS);
                if (!is_array($body) || !is_array($params)) {
                    throw new \InvalidArgumentException('Appeal form is invalid.');
                }
                $token = $body['csrf_token'] ?? null;
                if (
                    !is_string($token) || !$this->csrf->validates(
                        $request,
                        CsrfAction::COMMUNITY_APPEAL,
                        $csrf['cookie'],
                        $token
                    )
                ) {
                    $status = 403;
                    $error = 'community.appeal.csrf_failed';
                } else {
                    $submission = UuidV7::fromString($this->field($body, 'submission_id', 36));
                    if ($name === 'account.community.appeals.submit') {
                        $caseId = UuidV7::fromString($this->parameter($params, 'caseId'));
                        $this->appeals->submit(
                            $actor,
                            $tenant,
                            $submission,
                            $caseId,
                            $this->field($body, 'statement', 8000)
                        );
                        return $this->views->redirect('/account/community/appeals?saved=1', $csrf['cookie']);
                    }
                    if ($name === 'workspace.community.appeals.decide') {
                        $version = $this->field($body, 'expected_version', 10);
                        if (preg_match('/\A[1-9][0-9]{0,9}\z/', $version) !== 1) {
                            throw new \InvalidArgumentException('Appeal version is invalid.');
                        }
                        $appealId = UuidV7::fromString($this->parameter($params, 'appealId'));
                        $this->appeals->decide(
                            $actor,
                            $tenant,
                            $submission,
                            $appealId,
                            (int) $version,
                            $this->field($body, 'outcome', 16),
                            $this->field($body, 'reason_code', 48)
                        );
                        return $this->views->redirect('/workspace/community/appeals?saved=1', $csrf['cookie']);
                    }
                    throw new \InvalidArgumentException('Unknown appeal action.');
                }
            } catch (\InvalidArgumentException) {
                $status = 422;
                $error = 'community.appeal.invalid';
            } catch (\DomainException) {
                $status = 409;
                $error = 'community.appeal.unavailable';
            }
        }
        $items = $reviewer ? $this->appeals->queue($actor, $tenant)
            : $this->appeals->ownerEligible($actor, $tenant);
        $selected = '';
        $statement = '';
        if ($reviewer && $request->getMethod() === 'GET') {
            $params = $request->getAttribute(RouteAttributes::PARAMETERS);
            if (is_array($params) && is_string($params['appealId'] ?? null)) {
                try {
                    $selected = UuidV7::fromString($params['appealId'])->toString();
                    if (!in_array($selected, array_column($items, 'public_id'), true)) {
                        throw new \DomainException('Appeal unavailable.');
                    }
                    $statement = $this->appeals->statement($actor, $tenant, UuidV7::fromString($selected));
                } catch (\InvalidArgumentException | \DomainException) {
                    $status = 404;
                    $error = 'community.appeal.unavailable';
                    $selected = '';
                }
            }
        }
        foreach ($items as &$item) {
            $item['submission_id'] = UuidV7::generate()->toString();
        }
        unset($item);
        $view = $reviewer ? 'community-moderation-appeals' : 'community-owner-appeals';
        return $this->views->render(
            $request,
            'pages.' . $view,
            'fragments.' . $view,
            new ViewData(['items' => $items, 'csrf_token' => $csrf['token'], 'error' => $error,
                'selected' => $selected, 'statement' => $statement,
                'saved' => $request->getMethod() === 'GET'
                    && ($request->getQueryParams()['saved'] ?? null) === '1']),
            'community.appeal.title',
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
            throw new \InvalidArgumentException('Appeal form field is invalid.');
        }
        return trim($value);
    }

    /** @param array<array-key,mixed> $params */
    private function parameter(array $params, string $name): string
    {
        $value = $params[$name] ?? null;
        if (!is_string($value)) {
            throw new \InvalidArgumentException('Appeal route is invalid.');
        }
        return $value;
    }
}
