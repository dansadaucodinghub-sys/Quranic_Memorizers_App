<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace Qmdb\Modules\SecurityPrivilegedAccess\Application;

use Qmdb\Modules\SecurityAuthorization\Application\AuthenticationAssuranceComparator;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationDecision;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequest;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationService;
use Qmdb\Modules\SecurityAuthorization\Application\RoleBasedAuthorizationService;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationCatalog;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationDecisionReason;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionStatus;
use Qmdb\Modules\SecurityAuthorization\Domain\PrivilegedWorkspaceAuthorizationScope;
use Qmdb\Modules\SecurityAuthorization\Domain\WorkspaceAuthorizationScope;
use Qmdb\Shared\Time\Clock;

final readonly class PrivilegedAccessAuthorizationService implements AuthorizationService
{
    public function __construct(
        private RoleBasedAuthorizationService $baseRoles,
        private AuthorizationCatalog $catalog,
        private AuthenticationAssuranceComparator $assurance,
        private PrivilegedAccessAuthorizationRepository $activations,
        private Clock $clock,
    ) {
    }

    public function decide(AuthorizationRequest $request): AuthorizationDecision
    {
        if ($this->isAdministrationPermission($request->permission->value())) {
            return $this->baseRoles->decide($request);
        }
        if (!$request->scope instanceof PrivilegedWorkspaceAuthorizationScope) {
            $base = $this->baseRoles->decide($request);
            if ($base->isAllowed()) {
                return $base;
            }
            if (!$request->scope instanceof WorkspaceAuthorizationScope && $request->scope->type()->value !== 'PLATFORM') {
                return $base;
            }
        }
        $definition = $this->catalog->permission($request->permission);
        if ($definition === null) {
            return AuthorizationDecision::deny(AuthorizationDecisionReason::DENIED_UNKNOWN_PERMISSION);
        }
        if ($definition->scopeType !== $request->scope->type()) {
            return AuthorizationDecision::deny(AuthorizationDecisionReason::DENIED_SCOPE_MISMATCH);
        }
        if ($definition->status !== PermissionStatus::ACTIVE) {
            return AuthorizationDecision::deny(AuthorizationDecisionReason::DENIED_PERMISSION_NOT_ACTIVE);
        }
        if (!$this->assurance->satisfies($request->subject->assurance, $definition->requiredAssurance)) {
            return AuthorizationDecision::deny(AuthorizationDecisionReason::DENIED_INSUFFICIENT_ASSURANCE);
        }
        $workspaceId = match (true) {
            $request->scope instanceof WorkspaceAuthorizationScope => $request->scope->tenantContext->workspaceInternalId,
            $request->scope instanceof PrivilegedWorkspaceAuthorizationScope => $request->scope->workspaceInternalId,
            default => null,
        };
        $source = $this->activations->activeSource(
            $request->subject,
            $request->permission,
            $request->scope->type(),
            $workspaceId,
            $this->clock->now(),
        );

        return $source === null
            ? AuthorizationDecision::deny(AuthorizationDecisionReason::DENIED_NO_ROLE_ASSIGNMENT)
            : AuthorizationDecision::allow($source);
    }

    private function isAdministrationPermission(string $code): bool
    {
        return $code === 'platform.authorization.assign' || $code === 'workspace.authorization.assign'
            || str_starts_with($code, 'platform.temporary_privileges.')
            || str_starts_with($code, 'workspace.temporary_privileges.')
            || str_starts_with($code, 'platform.support_access.')
            || str_starts_with($code, 'workspace.support_access.')
            || str_starts_with($code, 'platform.break_glass.');
    }
}
