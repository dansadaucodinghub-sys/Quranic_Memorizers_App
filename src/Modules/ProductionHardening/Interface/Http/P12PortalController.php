<?php

declare(strict_types=1);

namespace Qmdb\Modules\ProductionHardening\Interface\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\ProductionHardening\Application\WebhookSubscriptionSecretIssuer;
use Qmdb\Modules\ProductionHardening\Domain\WebhookEndpointPolicy;
use Qmdb\Modules\ProductionHardening\Infrastructure\Persistence\MySqlProductionHardeningRepository;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfCookie;
use Qmdb\Modules\TenancyContext\Application\Exception\TenantContextRequiredException;
use Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Routing\RouteAttributes;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Presentation\View\ViewData;

final readonly class P12PortalController implements Controller
{
    public function __construct(
        private MySqlProductionHardeningRepository $repository,
        private WebhookEndpointPolicy $endpoints,
        private WebhookSubscriptionSecretIssuer $webhookSecrets,
        private AuthenticatedRequestGuard $authentication,
        private TenantContextRequiredGuard $tenancy,
        private IdentityCsrf $csrf,
        private IdentityAccessView $views,
        private Psr17Factory $responses,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $route = $request->getAttribute(RouteAttributes::NAME);
        if (!is_string($route)) {
            throw new \RuntimeException('P12 route name is unavailable.');
        }
        if (str_starts_with($route, 'api.v1.')) {
            return $this->api($request, $route);
        }
        $actor = $this->authentication->context($request);
        if ($actor === null) {
            return $this->authentication->rejection($request);
        }
        $workspace = str_starts_with($route, 'workspace.');
        $workspaceId = null;
        if ($workspace) {
            try {
                $workspaceId = $this->tenancy->require($request)->workspaceInternalId;
            } catch (TenantContextRequiredException) {
                return $this->views->redirect('/account/workspaces?context_required=1');
            }
        }
        $csrf = $this->csrf->issue($request, CsrfAction::P12_PRODUCTION_HARDENING);
        $extra = $this->emptyExtra();
        $status = 200;
        $error = '';
        if ($request->getMethod() === 'POST') {
            $body = $request->getParsedBody();
            $submittedToken = is_array($body) && is_string($body['csrf_token'] ?? null) ? $body['csrf_token'] : '';
            if (!is_array($body) || !$this->csrf->validates($request, CsrfAction::P12_PRODUCTION_HARDENING, $csrf['cookie'], $submittedToken)) {
                return $this->render($request, $route, $actor->accountInternalId, $workspaceId, [], 'p12.error.csrf', 403, $csrf);
            }
            $submissionId = '';
            $claimed = false;
            try {
                $requestHash = hash('sha256', json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                $submissionId = substr(hash('sha256', $route . '|' . $submittedToken . '|' . $requestHash), 0, 36);
                if (!$this->repository->claimAccountOperation($actor->accountInternalId, $workspaceId, $route, $submissionId, $requestHash)) {
                    throw new \DomainException('Duplicate operation submission.');
                }
                $claimed = true;
                $extra = $this->mutate($route, $body, $actor->accountInternalId, $workspaceId);
                $status = 201;
                $this->repository->completeAccountOperation($actor->accountInternalId, $route, $submissionId, $status, true);
            } catch (\InvalidArgumentException) {
                if ($claimed) {
                    $this->repository->completeAccountOperation($actor->accountInternalId, $route, $submissionId, 422, false);
                }
                $error = 'p12.error.invalid';
                $status = 422;
            } catch (\DomainException | \OverflowException) {
                if ($claimed) {
                    $this->repository->completeAccountOperation($actor->accountInternalId, $route, $submissionId, 409, false);
                }
                $error = 'p12.error.unavailable';
                $status = 409;
            }
        }
        return $this->render($request, $route, $actor->accountInternalId, $workspaceId, $extra, $error, $status, $csrf);
    }

    /**
     * @param array<array-key,mixed> $body
     * @return array<string,mixed>
     */
    private function mutate(string $route, array $body, int $accountId, ?int $workspaceId): array
    {
        if ($route === 'account.notifications.read') {
            $this->repository->markNotificationRead($accountId, UuidV7::fromString($this->field($body, 'notification_id', 36)));
            return ['success' => 'p12.success.notification_read'];
        }
        if ($route === 'account.privacy.request') {
            $id = $this->repository->createPrivacyRequest(
                $accountId,
                null,
                UuidV7::fromString($this->field($body, 'subject_id', 36)),
                $this->field($body, 'request_type', 32),
                $this->field($body, 'authority_code', 32),
            );
            return ['success' => 'p12.success.privacy_created', 'created_id' => $id->toString()];
        }
        if (str_ends_with($route, 'clients.create')) {
            $scopes = $body['scopes'] ?? [];
            if (!is_array($scopes)) {
                throw new \InvalidArgumentException('Scopes must be a list.');
            }
            $result = $this->repository->createApiClient(
                $workspaceId,
                $accountId,
                $this->field($body, 'client_code', 80),
                $this->field($body, 'display_name', 191),
                array_values(array_filter($scopes, 'is_string')),
            );
            return ['success' => 'p12.success.client_created', 'one_time_credential' => $result['credential'], 'created_id' => $result['client_id'], 'credential_expires' => $result['expires_at']];
        }
        if (str_ends_with($route, 'clients.rotate')) {
            $result = $this->repository->rotateApiCredential($workspaceId, UuidV7::fromString($this->field($body, 'client_id', 36)));
            return ['success' => 'p12.success.credential_rotated', 'one_time_credential' => $result['credential'], 'credential_expires' => $result['expires_at']];
        }
        if (str_ends_with($route, 'clients.revoke')) {
            $this->repository->revokeApiClient($workspaceId, UuidV7::fromString($this->field($body, 'client_id', 36)));
            return ['success' => 'p12.success.client_revoked'];
        }
        if (str_ends_with($route, 'webhooks.create')) {
            $endpoint = $this->field($body, 'endpoint_url', 1000);
            $this->endpoints->assertAllowed($endpoint);
            $secret = $this->webhookSecrets->issue();
            $id = $this->repository->createWebhookSubscription(
                $workspaceId,
                UuidV7::fromString($this->field($body, 'client_id', 36)),
                $this->field($body, 'event_code', 96),
                $endpoint,
                $secret['key_id'],
                $secret['ciphertext'],
            );
            return ['success' => 'p12.success.webhook_created', 'created_id' => $id->toString(), 'one_time_webhook_secret' => $secret['key_id'] . '.' . $secret['secret']];
        }
        if (str_ends_with($route, 'webhooks.suspend')) {
            $this->repository->suspendWebhook($workspaceId, UuidV7::fromString($this->field($body, 'webhook_id', 36)));
            return ['success' => 'p12.success.webhook_suspended'];
        }
        throw new \InvalidArgumentException('Unsupported P12 operation.');
    }

    private function api(ServerRequestInterface $request, string $route): ResponseInterface
    {
        $authorization = $request->getHeaderLine('Authorization');
        if (!str_starts_with($authorization, 'QMDB ')) {
            return $this->problem(401, 'AUTHENTICATION_REQUIRED', 'API client authentication is required.');
        }
        $client = $this->repository->authenticateApiCredential(substr($authorization, 5));
        if ($client === null) {
            return $this->problem(401, 'INVALID_CREDENTIAL', 'API credential is invalid or expired.');
        }
        $scope = $route === 'api.v1.results.index' ? 'projections.results.read' : null;
        if ($scope === null || !in_array($scope, $client['scopes'], true)) {
            return $this->problem(403, 'SCOPE_DENIED', 'The client scope does not permit this operation.');
        }
        $timestamp = $request->getHeaderLine('X-QMDB-Timestamp');
        $nonce = $request->getHeaderLine('X-QMDB-Nonce');
        if (preg_match('/\A[0-9]{10}\z/', $timestamp) !== 1) {
            return $this->problem(401, 'REPLAY_GUARD_REQUIRED', 'A valid request timestamp and nonce are required.');
        }
        try {
            $claimed = $this->repository->claimApiRequest($client, $route, $nonce, hash('sha256', $request->getMethod() . '|' . (string) $request->getUri()), (int) $timestamp);
        } catch (\OverflowException) {
            return $this->problem(429, 'QUOTA_EXCEEDED', 'API quota exceeded.')->withHeader('Retry-After', '60');
        }
        if (!$claimed) {
            return $this->problem(409, 'REPLAY_DETECTED', 'Duplicate or stale API request rejected.');
        }
        $after = $request->getQueryParams()['after'] ?? '0';
        $afterId = is_string($after) && preg_match('/\A[0-9]{1,20}\z/', $after) === 1 ? (int) $after : 0;
        $items = $this->repository->apiResultProjections($client['workspace_id'], $afterId, 25);
        $last = end($items);
        $nextAfter = is_array($last) ? $this->scalarText($last['id'] ?? null) : '';
        return $this->json(200, [
            'data' => $items,
            'meta' => ['version' => 'v1', 'next_after' => $nextAfter],
        ])->withHeader('Cache-Control', 'private, no-store')
            ->withHeader('X-QMDB-API-Version', '1')
            ->withHeader('Deprecation', 'false');
    }

    /**
     * @param array<string,mixed> $extra
     * @param array{token:string,cookie:CsrfCookie} $csrf
     */
    private function render(ServerRequestInterface $request, string $route, int $accountId, ?int $workspaceId, array $extra, string $error, int $status, array $csrf): ResponseInterface
    {
        $section = str_contains($route, 'notification') ? 'notifications'
            : (str_contains($route, 'privacy') ? 'privacy'
                : (str_contains($route, 'integration') || str_contains($route, 'webhook') ? 'integrations' : 'operations'));
        $platform = str_starts_with($route, 'platform.');
        $data = [
            'section' => $section, 'scope' => $platform ? 'PLATFORM' : ($workspaceId === null ? 'ACCOUNT' : 'WORKSPACE'),
            'error' => $error, 'success' => '', 'created_id' => '', 'one_time_credential' => '',
            'one_time_webhook_secret' => '', 'credential_expires' => '', 'csrf_token' => $csrf['token'],
            'submission_id' => UuidV7::generate()->toString(), 'summary' => $this->repository->operationalSummary(),
            'notifications' => [], 'privacy_requests' => [], 'clients' => [], 'webhooks' => [],
        ];
        if ($section === 'notifications') {
            $data['notifications'] = $this->repository->notifications($accountId);
        } elseif ($section === 'privacy') {
            $data['privacy_requests'] = $this->repository->privacyRequests($accountId, $platform, $workspaceId);
        } elseif ($section === 'integrations') {
            $data['clients'] = $this->repository->apiClients($workspaceId);
            $data['webhooks'] = $this->repository->webhookSubscriptions($workspaceId);
        }
        return $this->views->render(
            $request,
            'pages.p12-portal',
            'fragments.p12-portal',
            new ViewData(array_merge($data, $extra)),
            'p12.title',
            $status,
            $csrf['cookie'],
            true,
        )->withHeader('Cache-Control', 'private, no-store')->withHeader('Vary', 'Accept, X-Requested-With, Cookie');
    }

    /** @param array<array-key,mixed> $body */
    private function field(array $body, string $name, int $maximum): string
    {
        $value = $body[$name] ?? null;
        if (!is_string($value) || trim($value) === '' || mb_strlen($value) > $maximum) {
            throw new \InvalidArgumentException('P12 form field is invalid.');
        }
        return trim($value);
    }

    /** @param array<string,mixed> $payload */
    private function json(int $status, array $payload): ResponseInterface
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $response = $this->responses->createResponse($status)->withHeader('Content-Type', 'application/json; charset=utf-8');
        $response->getBody()->write($json);
        return $response;
    }

    private function problem(int $status, string $code, string $detail): ResponseInterface
    {
        return $this->json($status, ['type' => 'about:blank', 'title' => $code, 'status' => $status, 'detail' => $detail, 'code' => $code]);
    }

    /** @return array<string,mixed> */
    private function emptyExtra(): array
    {
        return [];
    }

    private function scalarText(mixed $value): string
    {
        return is_int($value) || is_string($value) ? (string) $value : '';
    }
}
