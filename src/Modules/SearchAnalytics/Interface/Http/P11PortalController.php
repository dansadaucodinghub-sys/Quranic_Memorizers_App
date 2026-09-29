<?php

declare(strict_types=1);

namespace Qmdb\Modules\SearchAnalytics\Interface\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\SearchAnalytics\Domain\ArabicSearchNormalizer;
use Qmdb\Modules\SearchAnalytics\Infrastructure\Persistence\MySqlP11Repository;
use Qmdb\Modules\SearchAnalytics\Infrastructure\Persistence\P11ExportDeliveryService;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfCookie;
use Qmdb\Modules\TenancyContext\Application\Exception\TenantContextRequiredException;
use Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Routing\RouteAttributes;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Presentation\View\ViewData;

final readonly class P11PortalController implements Controller
{
    public function __construct(
        private MySqlP11Repository $repository,
        private ArabicSearchNormalizer $normalizer,
        private AuthenticatedRequestGuard $authentication,
        private TenantContextRequiredGuard $tenancy,
        private IdentityCsrf $csrf,
        private IdentityAccessView $views,
        private P11ExportDeliveryService $exports,
        private Psr17Factory $responses,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $route = $request->getAttribute(RouteAttributes::NAME);
        if (!is_string($route)) {
            throw new \RuntimeException('P11 route name is unavailable.');
        }
        $public = str_starts_with($route, 'public.');
        $workspace = str_starts_with($route, 'workspace.');
        $actor = $this->authentication->context($request);
        if (!$public && $actor === null) {
            return $this->authentication->rejection($request);
        }
        $tenant = null;
        if ($workspace) {
            try {
                $tenant = $this->tenancy->require($request);
            } catch (TenantContextRequiredException) {
                return $this->views->redirect('/account/workspaces?context_required=1');
            }
        }
        $scope = $public ? 'PUBLIC' : ($workspace ? 'WORKSPACE' : 'PLATFORM');
        $workspaceId = $tenant?->workspaceInternalId;
        if (str_ends_with($route, 'export.download')) {
            if ($actor === null) {
                return $this->authentication->rejection($request);
            }
            return $this->download($request, $workspaceId, $actor->accountInternalId);
        }
        $csrf = $actor === null ? null : $this->csrf->issue($request, CsrfAction::P11_REPORT_REQUEST);
        $status = 200;
        $error = '';
        if ($request->getMethod() === 'POST') {
            try {
                $body = $request->getParsedBody();
                if ($actor === null || $csrf === null || !is_array($body)) {
                    throw new \InvalidArgumentException('Report request is invalid.');
                }
                $token = $this->field($body, 'csrf_token', 200);
                if (!$this->csrf->validates($request, CsrfAction::P11_REPORT_REQUEST, $csrf['cookie'], $token)) {
                    return $this->render($request, $route, $scope, $workspaceId, $actor->accountInternalId, [], 'analytics.csrf_failed', 403, $csrf);
                }
                if (str_ends_with($route, '.approve')) {
                    $parameters = $request->getAttribute(RouteAttributes::PARAMETERS);
                    if (!is_array($parameters) || !is_string($parameters['reportId'] ?? null)) {
                        throw new \InvalidArgumentException('Report approval identity is invalid.');
                    }
                    $this->repository->approveReport(
                        $actor->accountInternalId,
                        $workspaceId,
                        UuidV7::fromString($parameters['reportId']),
                        UuidV7::fromString($this->field($body, 'submission_id', 36)),
                    );
                    $base = $workspace ? '/workspace/reports/approvals' : '/platform/reports/approvals';
                    return $this->views->redirect($base . '?approved=1', $csrf['cookie']);
                }
                $runId = $this->repository->requestReport(
                    $actor->accountInternalId,
                    $workspaceId,
                    UuidV7::fromString($this->field($body, 'definition_id', 36)),
                    UuidV7::fromString($this->field($body, 'submission_id', 36)),
                    $this->field($body, 'purpose', 500),
                );
                $base = $workspace ? '/workspace/reports' : '/platform/reports';
                return $this->views->redirect($base . '?requested=' . rawurlencode($runId->toString()), $csrf['cookie']);
            } catch (\InvalidArgumentException) {
                $status = 422;
                $error = 'analytics.request_invalid';
            } catch (\DomainException) {
                $status = 409;
                $error = 'analytics.request_unavailable';
            }
        }
        return $this->render($request, $route, $scope, $workspaceId, $actor?->accountInternalId, [], $error, $status, $csrf);
    }

    private function download(ServerRequestInterface $request, ?int $workspaceId, int $actorAccountId): ResponseInterface
    {
        $parameters = $request->getAttribute(RouteAttributes::PARAMETERS);
        if (!is_array($parameters) || !is_string($parameters['exportId'] ?? null)) {
            return $this->responses->createResponse(404)->withHeader('Cache-Control', 'private, no-store');
        }
        try {
            $artifact = $this->exports->deliver(
                UuidV7::fromString($parameters['exportId']),
                $workspaceId,
                $actorAccountId,
            );
        } catch (\InvalidArgumentException | \DomainException) {
            return $this->responses->createResponse(404)->withHeader('Cache-Control', 'private, no-store');
        }
        $response = $this->responses->createResponse(200)
            ->withHeader('Content-Type', $artifact['media_type'])
            ->withHeader('Content-Disposition', 'attachment; filename="' . $artifact['filename'] . '"')
            ->withHeader('Content-Length', (string) strlen($artifact['contents']))
            ->withHeader('Digest', 'sha-256=' . base64_encode(hex2bin($artifact['checksum']) ?: ''))
            ->withHeader('Cache-Control', 'private, no-store')
            ->withHeader('Referrer-Policy', 'no-referrer')
            ->withHeader('X-Content-Type-Options', 'nosniff');
        $response->getBody()->write($artifact['contents']);
        return $response;
    }

    /**
     * @param array<string,mixed> $extra
     * @param array{token:string,cookie:CsrfCookie}|null $csrf
     */
    private function render(ServerRequestInterface $request, string $route, string $scope, ?int $workspaceId, ?int $accountId, array $extra, string $error, int $status, ?array $csrf): ResponseInterface
    {
        $query = $request->getQueryParams();
        $section = str_contains($route, 'search') ? 'search' : (str_contains($route, 'approv') ? 'approvals' : (str_contains($route, 'report') ? 'reports' : 'analytics'));
        $data = ['section' => $section, 'scope' => $scope, 'error' => $error, 'query' => '', 'items' => [],
            'dashboards' => [], 'definitions' => [], 'runs' => [], 'approvals' => [], 'next_after' => '',
            'csrf_token' => $csrf['token'] ?? '', 'submission_id' => UuidV7::generate()->toString()];
        if ($section === 'search') {
            $value = is_string($query['q'] ?? null) ? trim($query['q']) : '';
            if (mb_strlen($value) > 200) {
                $value = mb_substr($value, 0, 200);
            }
            $after = is_string($query['after'] ?? null) && preg_match('/\A[0-9]{1,20}\z/', $query['after']) === 1 ? (int) $query['after'] : 0;
            $items = $this->repository->search($this->normalizer->normalize($value), $workspaceId, $scope, $after, 25);
            $data['query'] = $value;
            $data['items'] = $items;
            $last = end($items);
            $lastId = is_array($last) ? ($last['id'] ?? null) : null;
            $data['next_after'] = is_int($lastId) || is_string($lastId) ? (string) $lastId : '';
        } elseif ($section === 'analytics') {
            $data['dashboards'] = $this->repository->dashboards($scope);
        } elseif ($section === 'reports') {
            $data['definitions'] = $this->repository->reportDefinitions($scope);
            $data['runs'] = $accountId === null ? [] : $this->repository->reportRuns($accountId, $workspaceId);
        } else {
            $data['approvals'] = $this->repository->approvalRuns($workspaceId);
        }
        $titleKey = $scope === 'PUBLIC'
            ? ($section === 'search' ? 'title.public_search' : 'title.public_statistics')
            : 'analytics.title';

        return $this->views->render(
            $request,
            'pages.p11-portal',
            'fragments.p11-portal',
            new ViewData(array_merge($data, $extra)),
            $titleKey,
            $status,
            $csrf['cookie'] ?? null,
            $scope !== 'PUBLIC',
        )->withHeader('Cache-Control', $scope === 'PUBLIC' ? 'public, max-age=60' : 'private, no-store')
            ->withHeader('Vary', 'Accept, X-Requested-With, Cookie');
    }

    /** @param array<array-key,mixed> $body */
    private function field(array $body, string $name, int $maximum): string
    {
        $value = $body[$name] ?? null;
        if (!is_string($value) || mb_strlen($value) > $maximum) {
            throw new \InvalidArgumentException('P11 form field is invalid.');
        }
        return trim($value);
    }
}
