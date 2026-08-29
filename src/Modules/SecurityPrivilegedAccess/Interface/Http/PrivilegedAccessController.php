<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace Qmdb\Modules\SecurityPrivilegedAccess\Interface\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityAccessView;
use Qmdb\Modules\IdentityAccess\Interface\Http\IdentityCsrf;
use Qmdb\Modules\IdentitySessions\Interface\Http\AuthenticatedRequestGuard;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationScopeType;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\WorkspaceAuthorizationScope;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\BreakGlassActivationCommand;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\BreakGlassActivationService;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessActivationService;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessApprovalCommand;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessApprovalService;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessEndService;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessHttpRepository;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessLifecycleRepository;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessRequestCancellationService;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessRequestCommand;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessRequestService;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessReviewCommand;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessReviewService;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessRevocationService;
use Qmdb\Modules\SecurityPrivilegedAccess\Configuration\PrivilegedAccessConfiguration;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessApprovalDecision;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessApprovalType;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessDuration;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessJustification;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessReasonCode;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessReference;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessRequestId;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessReviewOutcome;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessSubmissionId;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessType;
use Qmdb\Modules\TenancyContext\Application\TenantContextAttributes;
use Qmdb\Modules\TenancyContext\Domain\AccountWorkspaceTenantContext;
use Qmdb\Shared\Http\Contract\Controller;
use Qmdb\Shared\Http\Request\RequestContextAttributes;
use Qmdb\Shared\Http\Routing\RouteAttributes;
use Qmdb\Shared\Localization\LocaleContext;
use Qmdb\Shared\Observability\Correlation\CorrelationId;
use Qmdb\Shared\Presentation\View\ViewData;
use Qmdb\Modules\SecurityWeb\Csrf\CsrfAction;

/**
 * Private server-rendered privileged-access surface. Its mutation routes are CSRF-, origin-,
 * session-, and service-authorized; no client-supplied permissions are trusted outside validation.
 */
final readonly class PrivilegedAccessController implements Controller
{
    public function __construct(
        private AuthenticatedRequestGuard $guard,
        private IdentityCsrf $csrf,
        private IdentityAccessView $view,
        private Psr17Factory $responses,
        private PrivilegedAccessHttpRepository $projections,
        private PrivilegedAccessLifecycleRepository $lifecycle,
        private PrivilegedAccessRequestService $requests,
        private BreakGlassActivationService $breakGlass,
        private PrivilegedAccessApprovalService $approvals,
        private PrivilegedAccessRequestCancellationService $cancellations,
        private PrivilegedAccessActivationService $activations,
        private PrivilegedAccessRevocationService $revocations,
        private PrivilegedAccessEndService $end,
        private PrivilegedAccessReviewService $reviews,
        private PrivilegedAccessConfiguration $configuration,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = $this->guard->context($request);
        if ($actor === null) {
            return $this->guard->rejection($request);
        }
        $route = $request->getAttribute(RouteAttributes::NAME);
        if (!is_string($route)) {
            return $this->responses->createResponse(404);
        }
        $action = $this->csrfAction($route);
        $csrf = $this->csrf->issue($request, $action);
        if (strtoupper($request->getMethod()) === 'GET') {
            return $this->page($request, $actor, $route, $csrf);
        }
        $body = $this->body($request);
        if ($body === null) {
            return $this->responses->createResponse(415);
        }
        try {
            if (!$this->csrf->validates($request, $action, $csrf['cookie'], $this->string($body, 'csrf_token', 512))) {
                return $this->responses->createResponse(403);
            }
            match ($route) {
                'account.privileged_access.temporary.request.submit' => $this->submitRequest($request, $actor, $body, PrivilegedAccessType::TEMPORARY_PRIVILEGE),
                'account.privileged_access.support.request.submit' => $this->submitRequest($request, $actor, $body, PrivilegedAccessType::SUPPORT_ACCESS),
                'account.privileged_access.break_glass.activate.submit' => $this->submitRequest($request, $actor, $body, PrivilegedAccessType::BREAK_GLASS),
                'account.privileged_access.approve.submit', 'workspace.privileged_access.approve.submit' => $this->approve($request, $actor, $body, $route),
                'account.privileged_access.reject.submit' => $this->reject($request, $actor, $body),
                'account.privileged_access.cancel.submit' => $this->cancellations->cancel($actor, $this->requestId($request), $this->correlation($request)),
                'account.privileged_access.activate.submit' => $this->activate($request, $actor),
                'account.privileged_access.revoke.submit' => $this->revocations->revoke($actor, $this->requestId($request), $this->correlation($request), $this->workspaceScope($request)),
                'account.privileged_access.active.end.submit' => $this->end->end($actor, $this->correlation($request)),
                'account.privileged_access.review.submit' => $this->completeReview($request, $actor, $body),
                default => throw new \DomainException('Unsupported privileged-access mutation.'),
            };
        } catch (\InvalidArgumentException | \DomainException) {
            return $this->responses->createResponse(422)->withHeader('Cache-Control', 'private, no-store');
        }

        return $this->view->redirect('/account/security/privileged-access', $csrf['cookie'])
            ->withHeader('Cache-Control', 'private, no-store');
    }

    /** @param array<string, mixed> $body */
    private function submitRequest(
        ServerRequestInterface $request,
        \Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext $actor,
        array $body,
        PrivilegedAccessType $type,
    ): void {
        $scope = AuthorizationScopeType::from($this->string($body, 'scope', 16));
        if ($type === PrivilegedAccessType::SUPPORT_ACCESS && $scope !== AuthorizationScopeType::WORKSPACE) {
            throw new \DomainException('Support access must be limited to one workspace.');
        }
        $normalWorkspace = $this->normalWorkspace($request);
        $workspaceInternalId = null;
        $membershipInternalId = null;
        if ($scope === AuthorizationScopeType::WORKSPACE) {
            if ($type === PrivilegedAccessType::TEMPORARY_PRIVILEGE) {
                if (!$normalWorkspace instanceof AccountWorkspaceTenantContext) {
                    throw new \DomainException('Select your active workspace before requesting temporary workspace access.');
                }
                $workspaceInternalId = $normalWorkspace->workspaceInternalId;
                $membershipInternalId = $normalWorkspace->membershipInternalId();
            } else {
                $workspaceInternalId = $this->projections->activeWorkspaceInternalId($this->string($body, 'workspace_id', 36));
                if ($workspaceInternalId === null) {
                    throw new \DomainException('The requested workspace is unavailable.');
                }
            }
        }
        $maximum = match ($type) {
            PrivilegedAccessType::TEMPORARY_PRIVILEGE => $this->configuration->temporaryPrivilegeMaximumTtlSeconds,
            PrivilegedAccessType::SUPPORT_ACCESS => $this->configuration->supportAccessMaximumTtlSeconds,
            PrivilegedAccessType::BREAK_GLASS => $this->configuration->breakGlassMaximumTtlSeconds,
        };
        $command = new PrivilegedAccessRequestCommand(
            $actor,
            $type,
            $scope,
            $workspaceInternalId,
            $membershipInternalId,
            $this->permissions($body),
            PrivilegedAccessDuration::requested($this->positiveInteger($body, 'duration_seconds'), $maximum),
            PrivilegedAccessJustification::fromInput($this->string($body, 'justification', $this->configuration->justificationMaximumBytes), $this->configuration->justificationMaximumBytes),
            $type === PrivilegedAccessType::TEMPORARY_PRIVILEGE ? null : PrivilegedAccessReference::fromInput($this->string($body, 'reference_code', $this->configuration->referenceMaximumBytes), $this->configuration->referenceMaximumBytes),
            $this->locale($request),
            PrivilegedAccessSubmissionId::fromString($this->string($body, 'submission_id', 36)),
            $this->correlation($request),
        );
        if ($type === PrivilegedAccessType::BREAK_GLASS) {
            $this->breakGlass->activate(new BreakGlassActivationCommand($command));

            return;
        }
        $this->requests->request($command, $normalWorkspace === null ? null : new WorkspaceAuthorizationScope($normalWorkspace));
    }

    /** @param array<string, mixed> $body */
    private function approve(ServerRequestInterface $request, \Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext $actor, array $body, string $route): void
    {
        $requestId = $this->requestId($request);
        $snapshot = $this->requestSnapshot($requestId);
        $workspace = $this->workspaceScope($request);
        $type = $route === 'workspace.privileged_access.approve.submit'
            || ($snapshot->type === PrivilegedAccessType::TEMPORARY_PRIVILEGE && $snapshot->scope === AuthorizationScopeType::WORKSPACE)
            ? PrivilegedAccessApprovalType::WORKSPACE : PrivilegedAccessApprovalType::PLATFORM;
        $this->approvals->decide(new PrivilegedAccessApprovalCommand(
            $actor,
            $requestId,
            $type,
            PrivilegedAccessApprovalDecision::APPROVED,
            PrivilegedAccessDuration::requested($this->positiveInteger($body, 'duration_seconds'), $this->configuration->temporaryPrivilegeMaximumTtlSeconds),
            PrivilegedAccessReasonCode::from($this->string($body, 'reason_code', 48)),
            $this->correlation($request),
            $workspace?->tenantContext->workspaceInternalId,
            $workspace?->tenantContext->membershipInternalId(),
        ), $workspace);
    }

    /** @param array<string, mixed> $body */
    private function reject(ServerRequestInterface $request, \Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext $actor, array $body): void
    {
        $requestId = $this->requestId($request);
        $snapshot = $this->requestSnapshot($requestId);
        $workspace = $this->workspaceScope($request);
        $type = $snapshot->type === PrivilegedAccessType::TEMPORARY_PRIVILEGE && $snapshot->scope === AuthorizationScopeType::WORKSPACE
            ? PrivilegedAccessApprovalType::WORKSPACE : PrivilegedAccessApprovalType::PLATFORM;
        $this->approvals->decide(new PrivilegedAccessApprovalCommand(
            $actor,
            $requestId,
            $type,
            PrivilegedAccessApprovalDecision::REJECTED,
            PrivilegedAccessDuration::requested(1, $this->configuration->temporaryPrivilegeMaximumTtlSeconds),
            PrivilegedAccessReasonCode::REQUEST_REJECTED,
            $this->correlation($request),
            $workspace?->tenantContext->workspaceInternalId,
            $workspace?->tenantContext->membershipInternalId(),
        ), $workspace);
    }

    private function activate(ServerRequestInterface $request, \Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext $actor): void
    {
        $id = $this->requestId($request);
        $this->activations->activate($actor, $id, $this->requestSnapshot($id)->type, $this->correlation($request));
    }

    /** @param array<string, mixed> $body */
    private function completeReview(ServerRequestInterface $request, \Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext $actor, array $body): void
    {
        $reviewId = $this->projections->reviewForRequest($this->requestId($request));
        if ($reviewId === null) {
            throw new \DomainException('The privileged-access review is unavailable.');
        }
        $this->reviews->complete(new PrivilegedAccessReviewCommand(
            $actor,
            $reviewId,
            PrivilegedAccessReviewOutcome::from($this->string($body, 'outcome', 24)),
            $this->string($body, 'summary', $this->configuration->justificationMaximumBytes),
            $this->correlation($request),
        ));
    }

    /** @param array{cookie: \Qmdb\Modules\SecurityWeb\Csrf\CsrfCookie, token: string} $csrf */
    private function page(ServerRequestInterface $request, \Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext $actor, string $route, array $csrf): ResponseInterface
    {
        $mode = match ($route) {
            'account.privileged_access.temporary.request.form' => 'temporary_request',
            'account.privileged_access.support.request.form' => 'support_request',
            'account.privileged_access.break_glass.activate.form' => 'break_glass',
            'account.privileged_access.approve.form', 'workspace.privileged_access.approve.form' => 'approve',
            'account.privileged_access.review.form' => 'review',
            default => 'inventory',
        };

        return $this->view->render($request, 'pages.privileged-access', 'fragments.privileged-access-panel', new ViewData([
            'mode' => $mode,
            'csrf_token' => $csrf['token'],
            'submission_id' => PrivilegedAccessSubmissionId::generate()->toString(),
            'requests' => $this->projections->ownRequests($actor),
            'request_id' => $this->parameter($request, 'requestId', false),
        ]), 'title.privileged_access', cookie: $csrf['cookie'])->withHeader('Cache-Control', 'private, no-store');
    }

    private function requestSnapshot(PrivilegedAccessRequestId $requestId): \Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessRequestSnapshot
    {
        $snapshot = $this->lifecycle->requestSnapshot($requestId);
        if ($snapshot === null) {
            throw new \DomainException('The privileged-access request is unavailable.');
        }

        return $snapshot;
    }

    private function csrfAction(string $route): CsrfAction
    {
        return match ($route) {
            'account.privileged_access.temporary.request.form', 'account.privileged_access.temporary.request.submit' => CsrfAction::PRIVILEGED_ACCESS_TEMPORARY_REQUEST,
            'account.privileged_access.support.request.form', 'account.privileged_access.support.request.submit' => CsrfAction::PRIVILEGED_ACCESS_SUPPORT_REQUEST,
            'account.privileged_access.break_glass.activate.form', 'account.privileged_access.break_glass.activate.submit' => CsrfAction::PRIVILEGED_ACCESS_BREAK_GLASS_ACTIVATE,
            'account.privileged_access.approve.form', 'account.privileged_access.approve.submit', 'workspace.privileged_access.approve.form', 'workspace.privileged_access.approve.submit' => CsrfAction::PRIVILEGED_ACCESS_APPROVE,
            'account.privileged_access.reject.submit' => CsrfAction::PRIVILEGED_ACCESS_REJECT,
            'account.privileged_access.cancel.submit' => CsrfAction::PRIVILEGED_ACCESS_CANCEL,
            'account.privileged_access.activate.submit' => CsrfAction::PRIVILEGED_ACCESS_ACTIVATE,
            'account.privileged_access.revoke.submit' => CsrfAction::PRIVILEGED_ACCESS_REVOKE,
            'account.privileged_access.active.end.submit' => CsrfAction::PRIVILEGED_ACCESS_END,
            'account.privileged_access.review.form', 'account.privileged_access.review.submit' => CsrfAction::PRIVILEGED_ACCESS_REVIEW,
            'account.privileged_access.index' => CsrfAction::PRIVILEGED_ACCESS_END,
            default => CsrfAction::PRIVILEGED_ACCESS_CANCEL,
        };
    }

    /** @return array<string, mixed>|null */
    private function body(ServerRequestInterface $request): ?array
    {
        if (strtolower(trim(explode(';', $request->getHeaderLine('Content-Type'))[0])) !== 'application/x-www-form-urlencoded') {
            return null;
        }
        $body = $request->getParsedBody();
        if ($body === null) {
            parse_str((string) $request->getBody(), $body);
        }
        if (!is_array($body) || count($body) > 24) {
            return null;
        }
        foreach ($body as $key => $_) {
            if (!is_string($key)) {
                return null;
            }
        }

        return $body;
    }

    /**
     * @param array<string, mixed> $body
     * @return non-empty-list<PermissionCode>
     */
    private function permissions(array $body): array
    {
        $value = $this->string($body, 'permission_codes', 1024);
        $codes = array_values(array_filter(array_map('trim', explode(',', $value)), static fn (string $code): bool => $code !== ''));
        if ($codes === [] || count($codes) > $this->configuration->maximumPermissions) {
            throw new \InvalidArgumentException('Privileged-access permissions are invalid.');
        }

        $permissions = [];
        foreach ($codes as $code) {
            $permissions[] = new PermissionCode($code);
        }

        return $permissions;
    }

    /** @param array<string, mixed> $body */
    private function positiveInteger(array $body, string $field): int
    {
        $value = $this->string($body, $field, 12);
        if (preg_match('/\A[1-9][0-9]*\z/', $value) !== 1) {
            throw new \InvalidArgumentException('Privileged-access duration is invalid.');
        }

        return (int) $value;
    }

    /** @param array<string, mixed> $body */
    private function string(array $body, string $field, int $maximum): string
    {
        $value = $body[$field] ?? null;
        if (!is_string($value) || strlen($value) > $maximum || str_contains($value, "\0") || !mb_check_encoding($value, 'UTF-8')) {
            throw new \InvalidArgumentException('Privileged-access form input is invalid.');
        }

        return trim($value);
    }

    private function requestId(ServerRequestInterface $request): PrivilegedAccessRequestId
    {
        return PrivilegedAccessRequestId::fromString($this->parameter($request, 'requestId'));
    }

    private function parameter(ServerRequestInterface $request, string $name, bool $required = true): string
    {
        $parameters = $request->getAttribute(RouteAttributes::PARAMETERS);
        $value = is_array($parameters) ? ($parameters[$name] ?? null) : null;
        if (!is_string($value) && $required) {
            throw new \InvalidArgumentException('Privileged-access route parameter is invalid.');
        }

        return is_string($value) ? $value : '';
    }

    private function correlation(ServerRequestInterface $request): CorrelationId
    {
        $id = $request->getAttribute(RequestContextAttributes::REQUEST_ID);
        if (!$id instanceof CorrelationId) {
            throw new \RuntimeException('Request correlation is unavailable.');
        }

        return $id;
    }

    private function locale(ServerRequestInterface $request): string
    {
        $locale = $request->getAttribute(RequestContextAttributes::LOCALE);

        return $locale instanceof LocaleContext ? $locale->locale()->value() : 'en';
    }

    private function normalWorkspace(ServerRequestInterface $request): ?AccountWorkspaceTenantContext
    {
        $context = $request->getAttribute(TenantContextAttributes::CONTEXT);

        return $context instanceof AccountWorkspaceTenantContext ? $context : null;
    }

    private function workspaceScope(ServerRequestInterface $request): ?WorkspaceAuthorizationScope
    {
        $workspace = $this->normalWorkspace($request);

        return $workspace === null ? null : new WorkspaceAuthorizationScope($workspace);
    }
}
