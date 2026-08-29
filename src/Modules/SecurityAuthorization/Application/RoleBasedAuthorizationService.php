<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Application;

use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationCatalog;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationDecisionReason;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionStatus;
use Qmdb\Modules\SecurityAuthorization\Domain\Repository\EffectivePermissionRepository;
use Qmdb\Modules\SecurityAuthorization\Domain\WorkspaceAuthorizationScope;
use Qmdb\Shared\Configuration\Logging\LogLevel;
use Qmdb\Shared\Observability\Logging\EventLogger;
use Qmdb\Shared\Observability\Logging\LogEventName;
use Throwable;

final readonly class RoleBasedAuthorizationService implements AuthorizationService
{
    public function __construct(
        private AuthorizationCatalog $catalog,
        private EffectivePermissionRepository $permissions,
        private AuthenticationAssuranceComparator $assurance,
        private EventLogger $logger,
    ) {
    }

    public function decide(AuthorizationRequest $request): AuthorizationDecision
    {
        try {
            $decision = $this->evaluate($request);
        } catch (Throwable $exception) {
            $decision = AuthorizationDecision::deny(AuthorizationDecisionReason::DENIED_CATALOG_INCONSISTENCY);
            $this->log($request, $decision, hash('sha256', $exception::class));

            return $decision;
        }
        $this->log($request, $decision);

        return $decision;
    }

    private function evaluate(AuthorizationRequest $request): AuthorizationDecision
    {
        $registered = $this->catalog->permission($request->permission);
        if ($registered === null) {
            return AuthorizationDecision::deny(AuthorizationDecisionReason::DENIED_UNKNOWN_PERMISSION);
        }
        if ($registered->scopeType !== $request->scope->type()) {
            return AuthorizationDecision::deny(AuthorizationDecisionReason::DENIED_SCOPE_MISMATCH);
        }
        $persisted = $this->permissions->permission($request->permission);
        if (
            $persisted === null
            || $persisted->id->toString() !== $registered->id->toString()
            || $persisted->scopeType !== $registered->scopeType
            || $persisted->requiredAssurance !== $registered->requiredAssurance
        ) {
            return AuthorizationDecision::deny(AuthorizationDecisionReason::DENIED_CATALOG_INCONSISTENCY);
        }
        if ($persisted->status !== PermissionStatus::ACTIVE || $registered->status !== PermissionStatus::ACTIVE) {
            return AuthorizationDecision::deny(AuthorizationDecisionReason::DENIED_PERMISSION_NOT_ACTIVE);
        }
        if (!$this->permissions->accountIsActive($request->subject->accountInternalId)) {
            return AuthorizationDecision::deny(AuthorizationDecisionReason::DENIED_ACCOUNT_NOT_ACTIVE);
        }
        if (!$this->assurance->satisfies($request->subject->assurance, $persisted->requiredAssurance)) {
            return AuthorizationDecision::deny(AuthorizationDecisionReason::DENIED_INSUFFICIENT_ASSURANCE);
        }
        if ($request->scope instanceof WorkspaceAuthorizationScope) {
            if (
                $request->subject->accountInternalId !== $request->scope->tenantContext->accountInternalId
                || $request->subject->sessionInternalId !== $request->scope->tenantContext->sessionInternalId
            ) {
                return AuthorizationDecision::deny(AuthorizationDecisionReason::DENIED_SCOPE_MISMATCH);
            }
            if (!$this->permissions->workspaceIsActive($request->scope->tenantContext)) {
                return AuthorizationDecision::deny(AuthorizationDecisionReason::DENIED_WORKSPACE_NOT_ACTIVE);
            }
            if (
                !$this->permissions->membershipIsActive($request->scope->tenantContext)
            ) {
                return AuthorizationDecision::deny(AuthorizationDecisionReason::DENIED_MEMBERSHIP_NOT_ACTIVE);
            }
            $evidence = $this->permissions->findEffectiveWorkspacePermission(
                $request->scope->tenantContext,
                $request->permission,
            );
        } else {
            $evidence = $this->permissions->findEffectivePlatformPermission(
                $request->subject->accountInternalId,
                $request->permission,
            );
        }
        if (!$evidence->activeAssignmentExists) {
            return AuthorizationDecision::deny(AuthorizationDecisionReason::DENIED_NO_ROLE_ASSIGNMENT);
        }
        if (!$evidence->activeRoleExists) {
            return AuthorizationDecision::deny(AuthorizationDecisionReason::DENIED_ROLE_NOT_ACTIVE);
        }
        if (!$evidence->mappedPermissionExists) {
            return AuthorizationDecision::deny(AuthorizationDecisionReason::DENIED_NO_ROLE_ASSIGNMENT);
        }

        return AuthorizationDecision::allow();
    }

    private function log(
        AuthorizationRequest $request,
        AuthorizationDecision $decision,
        ?string $exceptionFingerprint = null,
    ): void {
        $context = [
            'actor_account_public_id' => $request->subject->accountId->toString(),
            'permission_code' => $request->permission->value(),
            'scope_type' => $request->scope->type()->value,
            'decision_reason' => $decision->reason->value,
            'assurance_level' => $request->subject->assurance->value,
        ];
        if ($request->scope instanceof WorkspaceAuthorizationScope) {
            $context['workspace_public_id'] = $request->scope->tenantContext->workspaceId->toString();
        }
        if ($exceptionFingerprint !== null) {
            $context['exception_fingerprint'] = $exceptionFingerprint;
        }
        $this->logger->log(
            $decision->isAllowed() ? LogLevel::DEBUG : LogLevel::WARNING,
            new LogEventName($decision->isAllowed()
                ? 'authorization.decision.allowed' : 'authorization.decision.denied'),
            $context,
        );
    }
}
