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

final readonly class CommunityCreatorClipController implements Controller
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
        $csrf = $this->csrf->issue($request, CsrfAction::COMMUNITY_CLIP_MUTATE);
        $error = '';
        $status = 200;
        if ($request->getMethod() === 'POST') {
            try {
                $body = $request->getParsedBody();
                if (!is_array($body)) {
                    throw new \InvalidArgumentException('Clip form is invalid.');
                }
                if (
                    !$this->csrf->validates(
                        $request,
                        CsrfAction::COMMUNITY_CLIP_MUTATE,
                        $csrf['cookie'],
                        $this->field($body, 'csrf_token', 200)
                    )
                ) {
                    $status = 403;
                    $error = 'community.creator.csrf_failed';
                } else {
                    $submission = UuidV7::fromString($this->field($body, 'submission_id', 36));
                    $route = $request->getAttribute(RouteAttributes::NAME);
                    if ($route === 'workspace.community.clips.create') {
                        $media = explode('|', $this->field($body, 'media', 73));
                        if (count($media) !== 2) {
                            throw new \InvalidArgumentException('Media selection is invalid.');
                        }
                        $this->clips->createFromSelection(
                            $actor,
                            $tenant,
                            $submission,
                            UuidV7::fromString($media[0]),
                            UuidV7::fromString($media[1]),
                            UuidV7::fromString($this->field($body, 'release_id', 36)),
                            $this->number($body, 'surah_number', 114),
                            $this->number($body, 'start_ayah', 286),
                            $this->number($body, 'end_ayah', 286),
                            $this->field($body, 'caption', 8000),
                            $this->field($body, 'language', 2),
                            $this->field($body, 'supersedes_clip_id', 36) === '' ? null
                                : UuidV7::fromString($this->field($body, 'supersedes_clip_id', 36))
                        );
                    } else {
                        $parameters = $request->getAttribute(RouteAttributes::PARAMETERS);
                        if (!is_array($parameters) || !is_string($parameters['clipId'] ?? null)) {
                            throw new \InvalidArgumentException('Clip identifier is missing.');
                        }
                        $clipId = UuidV7::fromString($parameters['clipId']);
                        $version = $this->number($body, 'expected_version', 2147483647);
                        match ($route) {
                            'workspace.community.clips.update' => $this->clips->updateDraft(
                                $actor,
                                $tenant,
                                $submission,
                                $clipId,
                                $version,
                                $this->field($body, 'caption', 8000),
                                $this->field($body, 'language', 2),
                                $this->field($body, 'comment_policy', 16)
                            ),
                            'workspace.community.clips.submit' => $this->clips->submit($actor, $tenant, $submission, $clipId, $version),
                            'workspace.community.clips.hide' => $this->clips->hideOwn($actor, $tenant, $submission, $clipId, $version),
                            'workspace.community.clips.remove' => $this->clips->removeOwn($actor, $tenant, $submission, $clipId, $version),
                            'workspace.community.clips.archive' => $this->clips->archiveOwn($actor, $tenant, $submission, $clipId, $version),
                            default => throw new \InvalidArgumentException('Unknown Clip action.'),
                        };
                    }
                    return $this->views->redirect('/workspace/community/clips?saved=1', $csrf['cookie']);
                }
            } catch (\InvalidArgumentException) {
                $status = 422;
                $error = 'community.creator.invalid';
            } catch (\DomainException) {
                $status = 409;
                $error = 'community.creator.unavailable';
            }
        }
        try {
            $workspace = $this->clips->creatorWorkspace($actor, $tenant);
        } catch (\DomainException) {
            $workspace = ['clips' => [], 'media' => [], 'passages' => []];
            $status = 403;
            $error = 'community.creator.unavailable';
        }
        foreach ($workspace['clips'] as &$clip) {
            $clip['submissions'] = [
                'update' => UuidV7::generate()->toString(),
                'submit' => UuidV7::generate()->toString(),
                'hide' => UuidV7::generate()->toString(),
                'remove' => UuidV7::generate()->toString(),
                'archive' => UuidV7::generate()->toString(),
            ];
        }
        unset($clip);
        return $this->views->render(
            $request,
            'pages.community-creator-clips',
            'fragments.community-creator-clips',
            new ViewData([
                'clips' => $workspace['clips'], 'media' => $workspace['media'],
                'passages' => $workspace['passages'], 'csrf_token' => $csrf['token'],
                'submission_id' => UuidV7::generate()->toString(), 'error' => $error,
                'saved' => $request->getMethod() === 'GET'
                    && ($request->getQueryParams()['saved'] ?? null) === '1',
            ]),
            'community.creator.title',
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
            throw new \InvalidArgumentException('Clip form field is invalid.');
        }
        return trim($value);
    }

    /** @param array<array-key,mixed> $body */
    private function number(array $body, string $name, int $maximum): int
    {
        $value = $this->field($body, $name, 10);
        if (preg_match('/\A[1-9][0-9]{0,9}\z/', $value) !== 1 || (int) $value > $maximum) {
            throw new \InvalidArgumentException('Clip form number is invalid.');
        }
        return (int) $value;
    }
}
