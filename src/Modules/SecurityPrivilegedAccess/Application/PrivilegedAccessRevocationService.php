<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace Qmdb\Modules\SecurityPrivilegedAccess\Application;

use Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventSubjectKind;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequest;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationSubject;
use Qmdb\Modules\SecurityAuthorization\Application\BaseRoleAuthorizationGuard;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationScope;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\PlatformAuthorizationScope;
use Qmdb\Modules\SecurityAuthorization\Domain\WorkspaceAuthorizationScope;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessRequestId;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessType;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Observability\Correlation\CorrelationId;
use Qmdb\Shared\Time\Clock;

/** Emergency removal is authorized by base roles and takes effect in the same transaction. */
final readonly class PrivilegedAccessRevocationService
{
    public function __construct(
        private BaseRoleAuthorizationGuard $authorization,
        private PrivilegedAccessLifecycleRepository $lifecycle,
        private StepUpGuard $stepUp,
        private PrivilegedAccessNotificationService $notifications,
        private SecurityAuditEventAppender $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {
    }

    public function revoke(
        AuthenticatedAccountContext $actor,
        PrivilegedAccessRequestId $requestId,
        CorrelationId $correlationId,
        ?WorkspaceAuthorizationScope $workspaceScope = null,
    ): PrivilegedAccessRequestSnapshot {
        $snapshot = $this->lifecycle->requestSnapshot($requestId);
        if ($snapshot === null) {
            throw new \DomainException('The privileged-access request is unavailable.');
        }
        [$permission, $scope, $action] = $this->authorizationPlan($snapshot, $workspaceScope);
        $request = new AuthorizationRequest(
            AuthorizationSubject::fromAuthenticatedContext($actor),
            new PermissionCode($permission),
            $scope,
        );
        $this->authorization->requireAllowed($request);

        return $this->transactions->transactional(function () use ($actor, $requestId, $correlationId, $request, $action): PrivilegedAccessRequestSnapshot {
            $this->authorization->requireAllowed($request);
            $this->stepUp->consume($actor, $action);
            $now = $this->clock->now();
            $revoked = $this->lifecycle->revoke($actor, $requestId, $correlationId->value(), $now);
            $this->notifications->create(
                $revoked->subjectAccountInternalId,
                match ($revoked->type) {
                    PrivilegedAccessType::TEMPORARY_PRIVILEGE => AccountSecurityNotificationType::TEMPORARY_PRIVILEGE_REVOKED,
                    PrivilegedAccessType::SUPPORT_ACCESS => AccountSecurityNotificationType::SUPPORT_ACCESS_REVOKED,
                    PrivilegedAccessType::BREAK_GLASS => AccountSecurityNotificationType::BREAK_GLASS_ENDED,
                },
                $revoked->id->toString(),
                $now,
            );
            $this->audit->privilegedAccess(
                match ($revoked->type) {
                    PrivilegedAccessType::TEMPORARY_PRIVILEGE => SecurityEventCode::TEMPORARY_REVOKED,
                    PrivilegedAccessType::SUPPORT_ACCESS => SecurityEventCode::SUPPORT_REVOKED,
                    PrivilegedAccessType::BREAK_GLASS => SecurityEventCode::BREAK_GLASS_REVOKED,
                },
                $revoked->scope->value === 'WORKSPACE',
                $revoked->workspacePublicId,
                SecurityEventSubjectKind::PRIVILEGED_ACCESS,
                $revoked->id->toString(),
                $actor->accountId->toString(),
                $now,
                ['access_type' => $revoked->type->value, 'scope_type' => $revoked->scope->value],
                null,
                $correlationId->value(),
            );

            return $revoked;
        });
    }

    /** @return array{string, AuthorizationScope, StepUpAction} */
    private function authorizationPlan(PrivilegedAccessRequestSnapshot $request, ?WorkspaceAuthorizationScope $workspaceScope): array
    {
        if ($request->type === PrivilegedAccessType::TEMPORARY_PRIVILEGE && $request->scope->value === 'WORKSPACE') {
            if ($workspaceScope === null || $workspaceScope->tenantContext->workspaceInternalId !== $request->workspaceInternalId) {
                throw new \DomainException('A current active workspace membership is required to revoke this request.');
            }

            return ['workspace.temporary_privileges.revoke', $workspaceScope, StepUpAction::TEMPORARY_PRIVILEGE_REVOKE];
        }
        if ($request->type === PrivilegedAccessType::SUPPORT_ACCESS) {
            return ['platform.temporary_privileges.revoke', new PlatformAuthorizationScope(), StepUpAction::SUPPORT_ACCESS_REVOKE];
        }

        return [
            'platform.temporary_privileges.revoke',
            new PlatformAuthorizationScope(),
            $request->type === PrivilegedAccessType::BREAK_GLASS
                ? StepUpAction::BREAK_GLASS_ACTIVATE : StepUpAction::TEMPORARY_PRIVILEGE_REVOKE,
        ];
    }
}
