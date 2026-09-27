<?php

declare(strict_types=1);

namespace Qmdb\Modules\PilotOfflineRollout\Interface\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\PilotOfflineRollout\Infrastructure\Persistence\MySqlPilotOfflineRolloutRepository;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfCookie;
use Qmdb\Modules\TenancyContext\Application\Exception\TenantContextRequiredException;
use Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Routing\RouteAttributes;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Presentation\View\ViewData;

final readonly class P13PortalController implements Controller
{
    public function __construct(
        private MySqlPilotOfflineRolloutRepository $repository,
        private AuthenticatedRequestGuard $authentication,
        private TenantContextRequiredGuard $tenancy,
        private IdentityCsrf $csrf,
        private IdentityAccessView $views,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $route = $request->getAttribute(RouteAttributes::NAME);
        if (!is_string($route)) {
            throw new \RuntimeException('P13 route name is unavailable.');
        }
        $actor = $this->authentication->context($request);
        if ($actor === null) {
            return $this->authentication->rejection($request);
        }
        $workspaceId = null;
        if (str_starts_with($route, 'workspace.')) {
            try {
                $workspaceId = $this->tenancy->require($request)->workspaceInternalId;
            } catch (TenantContextRequiredException) {
                return $this->views->redirect('/account/workspaces?context_required=1');
            }
        }
        $csrf = $this->csrf->issue($request, CsrfAction::P13_PILOT_OFFLINE_ROLLOUT);
        $extra = [];
        $status = 200;
        $error = '';
        if ($request->getMethod() === 'POST') {
            $body = $request->getParsedBody();
            $token = is_array($body) && is_string($body['csrf_token'] ?? null) ? $body['csrf_token'] : '';
            if (!is_array($body) || !$this->csrf->validates($request, CsrfAction::P13_PILOT_OFFLINE_ROLLOUT, $csrf['cookie'], $token)) {
                return $this->render($request, $route, $workspaceId, [], 'p13.error.csrf', 403, $csrf);
            }
            $scopeKind = $workspaceId === null ? 'PLATFORM' : 'WORKSPACE';
            $scopeReference = $workspaceId === null ? 'platform' : (string) $workspaceId;
            $requestHash = hash('sha256', json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $submission = is_string($body['submission_id'] ?? null) ? $body['submission_id'] : '';
            try {
                if (!$this->repository->claimOperation($scopeKind, $scopeReference, $route, $submission, $requestHash)) {
                    throw new \DomainException('Duplicate P13 operation submission.');
                }
                $resource = $this->mutate($route, $body, $actor->accountInternalId, $workspaceId, $request);
                $this->repository->completeOperation($scopeKind, $scopeReference, $route, $submission, true, $resource);
                $extra = ['success' => 'p13.success'];
                $status = 201;
            } catch (\InvalidArgumentException) {
                $this->repository->completeOperation($scopeKind, $scopeReference, $route, $submission, false);
                $error = 'p13.error.invalid';
                $status = 422;
            } catch (\DomainException | \OverflowException) {
                $this->repository->completeOperation($scopeKind, $scopeReference, $route, $submission, false);
                $error = 'p13.error.unavailable';
                $status = 409;
            }
        }

        return $this->render($request, $route, $workspaceId, $extra, $error, $status, $csrf);
    }

    /** @param array<array-key,mixed> $body */
    private function mutate(string $route, array $body, int $actor, ?int $workspaceId, ServerRequestInterface $request): ?UuidV7
    {
        if ($route === 'platform.pilots.create') {
            return $this->repository->createPilot($this->field($body, 'code', 80), $this->field($body, 'name', 191), $this->field($body, 'scope', 24), $this->field($body, 'starts_at', 32), $this->field($body, 'ends_at', 32), $actor);
        }
        if (str_starts_with($route, 'platform.pilots.') && !str_ends_with($route, '.sites') && !str_ends_with($route, '.incidents')) {
            $targets = ['readiness' => 'READINESS_REVIEW', 'approve' => 'APPROVED', 'start' => 'ACTIVE', 'pause' => 'PAUSED', 'resume' => 'ACTIVE', 'complete' => 'COMPLETED', 'cancel' => 'CANCELLED'];
            $action = substr($route, strrpos($route, '.') + 1);
            if (isset($targets[$action])) {
                $this->repository->transitionPilot($this->routeId($request, 'pilotId'), $targets[$action], $this->integer($body, 'expected_version'), $actor, $this->field($body, 'reason_code', 64, 'GOVERNED_ACTION'));
                return null;
            }
        }
        if ($route === 'platform.rollouts.create') {
            return $this->repository->createRollout($this->field($body, 'code', 80), $this->field($body, 'name', 191), $this->field($body, 'scope', 24), $this->integer($body, 'maximum_wave_size'), $actor);
        }
        if ($route === 'platform.rollouts.waves.create') {
            return $this->repository->createWave($this->routeId($request, 'rolloutId'), $this->field($body, 'name', 191), $this->field($body, 'starts_at', 32), $this->field($body, 'ends_at', 32), $actor);
        }
        if ($workspaceId !== null && $route === 'workspace.offline_devices.create') {
            $key = base64_decode($this->field($body, 'public_key', 128), true);
            if (!is_string($key)) {
                throw new \InvalidArgumentException('Device public key is invalid.');
            }
            return $this->repository->registerDevice($workspaceId, UuidV7::fromString($this->field($body, 'venue_id', 36)), $this->field($body, 'code', 80), $this->field($body, 'name', 191), $this->field($body, 'device_type', 24), $this->field($body, 'platform', 32), $key, $actor);
        }
        if ($workspaceId !== null && str_starts_with($route, 'workspace.offline_devices.')) {
            $targets = ['activate' => 'ACTIVE', 'suspend' => 'SUSPENDED', 'revoke' => 'REVOKED'];
            $action = substr($route, strrpos($route, '.') + 1);
            if (isset($targets[$action])) {
                $this->repository->transitionDevice($workspaceId, $this->routeId($request, 'deviceId'), $targets[$action], $this->integer($body, 'expected_version'), $actor);
                return null;
            }
        }
        if ($workspaceId !== null && $route === 'workspace.offline_packages.create') {
            return $this->repository->preparePackage($workspaceId, UuidV7::fromString($this->field($body, 'device_id', 36)), UuidV7::fromString($this->field($body, 'edition_id', 36)), [], $actor);
        }
        if ($workspaceId !== null && $route === 'workspace.offline_packages.revoke') {
            $this->repository->revokePackage($workspaceId, $this->routeId($request, 'packageId'), $actor);
            return null;
        }
        if ($workspaceId !== null && str_starts_with($route, 'workspace.offline_conflicts.')) {
            $decisions = ['accept_server' => 'ACCEPT_SERVER', 'accept_client' => 'ACCEPT_CLIENT_PROPOSAL', 'resolve_manually' => 'MANUAL', 'dismiss' => 'DISMISS'];
            $action = substr($route, strrpos($route, '.') + 1);
            if (isset($decisions[$action])) {
                $this->repository->decideConflict($workspaceId, $this->routeId($request, 'conflictId'), $decisions[$action], $this->integer($body, 'expected_version'), $this->field($body, 'reason_code', 64), $actor);
                return null;
            }
        }
        throw new \InvalidArgumentException('Unsupported P13 operation.');
    }

    /**
     * @param array<string,mixed> $extra
     * @param array{token:string,cookie:CsrfCookie} $csrf
     */
    private function render(ServerRequestInterface $request, string $route, ?int $workspaceId, array $extra, string $error, int $status, array $csrf): ResponseInterface
    {
        $section = str_contains($route, 'pilot') ? 'pilots' : (str_contains($route, 'rollout') ? 'rollouts' : (str_contains($route, 'package') ? 'packages' : (str_contains($route, 'sync') ? 'sync' : (str_contains($route, 'conflict') ? 'conflicts' : 'devices'))));
        $data = [
            'section' => $section, 'scope' => $workspaceId === null ? 'PLATFORM' : 'WORKSPACE',
            'csrf_token' => $csrf['token'], 'submission_id' => UuidV7::generate()->toString(),
            'error' => $error, 'success' => $extra['success'] ?? '', 'summary' => $this->repository->summary($workspaceId),
            'pilots' => $workspaceId === null ? $this->repository->pilots() : [],
            'rollouts' => $workspaceId === null ? $this->repository->rollouts() : [],
            'devices' => $workspaceId === null ? [] : $this->repository->devices($workspaceId),
            'packages' => $workspaceId === null ? [] : $this->repository->packages($workspaceId),
            'sync_sessions' => $workspaceId === null ? [] : $this->repository->syncSessions($workspaceId),
            'conflicts' => $workspaceId === null ? [] : $this->repository->conflicts($workspaceId),
        ];

        return $this->views->render($request, 'pages.p13-portal', 'fragments.p13-portal', new ViewData($data), 'p13.title', $status, $csrf['cookie'], true);
    }

    /** @param array<array-key,mixed> $body */
    private function field(array $body, string $name, int $maximum, ?string $default = null): string
    {
        $value = $body[$name] ?? $default;
        if (!is_string($value) || trim($value) === '' || mb_strlen($value) > $maximum) {
            throw new \InvalidArgumentException('P13 field is invalid.');
        }

        return trim($value);
    }

    /** @param array<array-key,mixed> $body */
    private function integer(array $body, string $name): int
    {
        $value = $body[$name] ?? null;
        if ((!is_string($value) && !is_int($value)) || preg_match('/\A[1-9][0-9]{0,9}\z/', (string) $value) !== 1) {
            throw new \InvalidArgumentException('P13 integer field is invalid.');
        }

        return (int) $value;
    }

    private function routeId(ServerRequestInterface $request, string $name): UuidV7
    {
        $parameters = $request->getAttribute(RouteAttributes::PARAMETERS, []);
        if (!is_array($parameters) || !is_string($parameters[$name] ?? null)) {
            throw new \InvalidArgumentException('P13 route identifier is invalid.');
        }

        return UuidV7::fromString($parameters[$name]);
    }
}
