<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Interface\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\Community\Application\CommunityPublicClipService;
use Qmdb\Modules\Community\Application\CommunityReportService;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Routing\RouteAttributes;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Presentation\View\ViewData;

final readonly class CommunityReportController implements Controller
{
    public function __construct(
        private AuthenticatedRequestGuard $authentication,
        private IdentityCsrf $csrf,
        private CommunityPublicClipService $clips,
        private CommunityReportService $reports,
        private IdentityAccessView $views,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = $this->authentication->context($request);
        if ($actor === null) {
            return $this->authentication->rejection($request);
        }
        $csrf = $this->csrf->issue($request, CsrfAction::COMMUNITY_REPORT);
        $error = '';
        $status = 200;
        $clipId = '';
        $submitted = ($request->getQueryParams()['submitted'] ?? null) === '1';
        try {
            if ($request->getMethod() === 'POST') {
                $body = $request->getParsedBody();
                if (!is_array($body)) {
                    throw new \InvalidArgumentException('Report form is invalid.');
                }
                $clipId = $this->field($body, 'clip_id');
                if (
                    !$this->csrf->validates(
                        $request,
                        CsrfAction::COMMUNITY_REPORT,
                        $csrf['cookie'],
                        $this->field($body, 'csrf_token')
                    )
                ) {
                    $status = 403;
                    $error = 'community.report.csrf_failed';
                } else {
                    $this->reports->submit(
                        $actor,
                        UuidV7::fromString($this->field($body, 'submission_id')),
                        UuidV7::fromString($clipId),
                        $this->field($body, 'reason_code'),
                        $this->field($body, 'statement')
                    );
                    return $this->views->redirect('/clips/' . $clipId . '/report?submitted=1', $csrf['cookie']);
                }
            } else {
                $parameters = $request->getAttribute(RouteAttributes::PARAMETERS);
                if (!is_array($parameters) || !is_string($parameters['clipId'] ?? null)) {
                    throw new \InvalidArgumentException('Clip identifier is missing.');
                }
                $clipId = $parameters['clipId'];
            }
            if (
                !$submitted && $this->clips->detail(
                    UuidV7::fromString($clipId),
                    $actor->accountInternalId
                ) === null
            ) {
                $status = 404;
                $error = 'community.report.unavailable';
            }
        } catch (\InvalidArgumentException) {
            $status = 422;
            $error = 'community.report.invalid';
        } catch (\DomainException) {
            $status = 409;
            $error = 'community.report.unavailable';
        }
        return $this->views->render(
            $request,
            'pages.community-report',
            'fragments.community-report',
            new ViewData([
                'clip_id' => $clipId, 'csrf_token' => $csrf['token'],
                'submission_id' => UuidV7::generate()->toString(),
                'error' => $error, 'submitted' => $submitted,
            ]),
            'community.report.title',
            $status,
            $csrf['cookie'],
            true
        );
    }

    /** @param array<array-key,mixed> $body */
    private function field(array $body, string $name): string
    {
        $value = $body[$name] ?? null;
        if (!is_string($value) || strlen($value) > 4096) {
            throw new \InvalidArgumentException('Report form field is invalid.');
        }
        return trim($value);
    }
}
