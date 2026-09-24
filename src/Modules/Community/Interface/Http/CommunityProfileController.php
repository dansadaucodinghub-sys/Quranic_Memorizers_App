<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Interface\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\Community\Application\CommunityProfileService;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Modules\TenancyContext\Application\Exception\TenantContextRequiredException;
use Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Presentation\View\ViewData;

final readonly class CommunityProfileController implements Controller
{
    public function __construct(
        private AuthenticatedRequestGuard $authentication,
        private TenantContextRequiredGuard $tenant,
        private IdentityCsrf $csrf,
        private CommunityProfileService $profiles,
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
            $tenant = $this->tenant->require($request);
        } catch (TenantContextRequiredException) {
            return $this->views->redirect('/account/workspaces?context_required=1');
        }
        $csrf = $this->csrf->issue($request, CsrfAction::COMMUNITY_PROFILE_SAVE);
        $current = $this->profiles->mine($actor);
        $error = '';
        $status = 200;
        $alias = $current['alias'] ?? '';
        $visibility = $current['visibility'] ?? 'PRIVATE';
        if ($request->getMethod() === 'POST') {
            try {
                $body = $request->getParsedBody();
                if (!is_array($body)) {
                    throw new \InvalidArgumentException('Profile form is invalid.');
                }
                $alias = $this->field($body, 'alias', 200);
                $visibility = $this->field($body, 'visibility', 16);
                if (
                    !$this->csrf->validates(
                        $request,
                        CsrfAction::COMMUNITY_PROFILE_SAVE,
                        $csrf['cookie'],
                        $this->field($body, 'csrf_token', 200)
                    )
                ) {
                    $status = 403;
                    $error = 'community.profile.csrf_failed';
                } else {
                    $submittedProfile = $this->field($body, 'profile_id', 36);
                    $profileId = $submittedProfile === '' ? null : UuidV7::fromString($submittedProfile);
                    $version = $this->version($this->field($body, 'expected_version', 10));
                    $this->profiles->save(
                        $actor,
                        $tenant,
                        UuidV7::fromString($this->field($body, 'submission_id', 36)),
                        $profileId,
                        $version,
                        $alias,
                        $visibility
                    );
                    return $this->views->redirect('/account/community/profile?saved=1', $csrf['cookie']);
                }
            } catch (\InvalidArgumentException) {
                $status = 422;
                $error = 'community.profile.invalid';
            } catch (\DomainException) {
                $status = 409;
                $error = 'community.profile.unavailable';
            }
        }
        return $this->views->render(
            $request,
            'pages.community-profile',
            'fragments.community-profile',
            new ViewData(['alias' => $alias, 'visibility' => $visibility,
                'profile_id' => $current['public_id'] ?? '',
                'expected_version' => $current['version'] ?? 0,
                'submission_id' => UuidV7::generate()->toString(),
                'csrf_token' => $csrf['token'], 'error' => $error,
                'saved' => $request->getMethod() === 'GET'
                    && ($request->getQueryParams()['saved'] ?? null) === '1']),
            'community.profile.title',
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
            throw new \InvalidArgumentException('Community profile field is invalid.');
        }
        return trim($value);
    }

    private function version(string $value): int
    {
        if (preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/', $value) !== 1) {
            throw new \InvalidArgumentException('Community profile version is invalid.');
        }
        return (int) $value;
    }
}
