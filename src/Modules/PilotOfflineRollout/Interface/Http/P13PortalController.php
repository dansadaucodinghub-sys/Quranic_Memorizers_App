<?php

declare(strict_types=1);

namespace Qmdb\Modules\PilotOfflineRollout\Interface\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitAttempt;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitPolicy;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitScope;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\PilotOfflineRollout\Infrastructure\Persistence\MySqlPilotOfflineRolloutRepository;
use Qmdb\Modules\PilotOfflineRollout\Application\P13AdministrativeMutationService;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequest;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequirementGuard;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationSubject;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\PlatformAuthorizationScope;
use Qmdb\Modules\SecurityAuthorization\Domain\WorkspaceAuthorizationScope;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfCookie;
use Qmdb\Modules\TenancyContext\Application\Exception\TenantContextRequiredException;
use Qmdb\Modules\TenancyContext\Application\TenantContextRequiredGuard;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Routing\RouteAttributes;
use Qmdb\Shared\Http\Routing\Security\ProductionRouteSecurityPolicyCatalog;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Database\Transaction\TransactionOptions;
use Qmdb\Shared\Database\Transaction\TransactionRetryPolicy;
use Qmdb\Shared\Presentation\View\ViewData;
use Qmdb\Shared\Time\Clock;

final readonly class P13PortalController implements Controller
{
    public function __construct(
        private MySqlPilotOfflineRolloutRepository $repository,
        private P13AdministrativeMutationService $mutations,
        private AuthenticatedRequestGuard $authentication,
        private TenantContextRequiredGuard $tenancy,
        private AuthorizationRequirementGuard $authorization,
        private StepUpGuard $stepUp,
        private IdentityRateLimiter $rateLimits,
        private IdentityFingerprintGenerator $fingerprints,
        private Clock $clock,
        private IdentityCsrf $csrf,
        private IdentityAccessView $views,
        private TransactionManager $transactions,
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
        $tenant = null;
        if (str_starts_with($route, 'workspace.')) {
            try {
                $tenant = $this->tenancy->require($request);
                $workspaceId = $tenant->workspaceInternalId;
            } catch (TenantContextRequiredException) {
                return $this->views->redirect('/account/workspaces?context_required=1');
            }
        }
        $csrf = $this->csrf->issue($request, CsrfAction::P13_PILOT_OFFLINE_ROLLOUT);
        $policy = (new ProductionRouteSecurityPolicyCatalog())->policies()[$route] ?? throw new \LogicException('P13 route security policy is unavailable.');
        if ($policy->permissionCode === null) {
            throw new \LogicException('P13 browser route permission is unavailable.');
        }
        try {
            $scope = $tenant === null ? new PlatformAuthorizationScope() : new WorkspaceAuthorizationScope($tenant);
            $this->authorization->requireAllowed(new AuthorizationRequest(
                AuthorizationSubject::fromAuthenticatedContext($actor),
                new PermissionCode($policy->permissionCode),
                $scope,
            ));
        } catch (\DomainException) {
            return $this->render($request, $route, $workspaceId, [], 'p13.error.unavailable', 403, $csrf);
        }
        $extra = [];
        $status = 200;
        $error = '';
        if ($request->getMethod() === 'POST') {
            $body = $request->getParsedBody();
            $token = is_array($body) && is_string($body['csrf_token'] ?? null) ? $body['csrf_token'] : '';
            if (!is_array($body) || !$this->csrf->validates($request, CsrfAction::P13_PILOT_OFFLINE_ROLLOUT, $csrf['cookie'], $token)) {
                return $this->render($request, $route, $workspaceId, [], 'p13.error.csrf', 403, $csrf);
            }
            $action = $policy->stepUpAction === null ? null : StepUpAction::tryFrom($policy->stepUpAction);
            if (!$action instanceof StepUpAction) {
                throw new \LogicException('P13 mutation Step-Up action is unavailable.');
            }
            $scopeKind = $workspaceId === null ? 'PLATFORM' : 'WORKSPACE';
            $scopeReference = $workspaceId === null ? 'platform' : (string) $workspaceId;
            $requestHash = hash('sha256', json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $submission = is_string($body['submission_id'] ?? null) ? $body['submission_id'] : '';
            try {
                if (!$this->repository->claimOperation($scopeKind, $scopeReference, $route, $submission, $requestHash)) {
                    throw new \DomainException('Duplicate P13 operation submission.');
                }
                $attempt = new IdentityRateLimitAttempt(
                    IdentityRateLimitScope::P13_ADMINISTRATIVE_MUTATION_ACCOUNT,
                    $this->fingerprints->generate('p13-administrative-mutation', (string) $actor->accountInternalId),
                    new IdentityRateLimitPolicy(60, 30, 60),
                );
                if (!$this->rateLimits->consume([$attempt], $this->clock->now())->allowed) {
                    throw new \OverflowException('P13 administrative mutation rate limit exceeded.');
                }
                $resource = $this->transactions->transactional(function () use ($request, $route, $body, $actor, $tenant, $action, $scopeKind, $scopeReference, $submission): ?UuidV7 {
                    $this->stepUp->consume($actor, $action);
                    $parameters = $request->getAttribute(RouteAttributes::PARAMETERS, []);
                    if (!is_array($parameters)) {
                        throw new \InvalidArgumentException('P13 route parameters are invalid.');
                    }
                    /** @var array<string,string> $parameters */
                    $resource = $this->mutations->execute($route, $body, $actor, $tenant, $parameters);
                    $this->repository->completeOperation($scopeKind, $scopeReference, $route, $submission, true, $resource);

                    return $resource;
                }, TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 15, 150)));
                $extra = ['success' => 'p13.success'];
                $status = 201;
            } catch (\InvalidArgumentException) {
                $this->repository->completeOperation($scopeKind, $scopeReference, $route, $submission, false);
                $error = 'p13.error.invalid';
                $status = 422;
            } catch (\OverflowException) {
                $this->repository->completeOperation($scopeKind, $scopeReference, $route, $submission, false);
                $error = 'p13.error.unavailable';
                $status = 429;
            } catch (\DomainException) {
                $this->repository->completeOperation($scopeKind, $scopeReference, $route, $submission, false);
                $error = 'p13.error.unavailable';
                $status = 409;
            } catch (\Throwable $failure) {
                $this->repository->completeOperation($scopeKind, $scopeReference, $route, $submission, false);
                throw $failure;
            }
        }

        return $this->render($request, $route, $workspaceId, $extra, $error, $status, $csrf);
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
            'rollout_waves' => $workspaceId === null ? $this->repository->rolloutWaves() : [],
            'devices' => $workspaceId === null ? [] : $this->repository->devices($workspaceId),
            'packages' => $workspaceId === null ? [] : $this->repository->packages($workspaceId),
            'sync_sessions' => $workspaceId === null ? [] : $this->repository->syncSessions($workspaceId),
            'conflicts' => $workspaceId === null ? [] : $this->repository->conflicts($workspaceId),
        ];

        return $this->views->render($request, 'pages.p13-portal', 'fragments.p13-portal', new ViewData($data), 'p13.title', $status, $csrf['cookie'], true);
    }
}
