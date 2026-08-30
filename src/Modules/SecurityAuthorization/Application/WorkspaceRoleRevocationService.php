<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Application;

use Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventSubjectKind;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationScopeType;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\Repository\AuthorizationAdministrationRepository;
use Qmdb\Modules\SecurityAuthorization\Domain\Repository\WorkspaceRoleAssignmentRepository;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleAssignmentActorKind;
use Qmdb\Modules\SecurityAuthorization\Domain\WorkspaceAuthorizationScope;
use Qmdb\Shared\Configuration\Logging\LogLevel;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Observability\Logging\EventLogger;
use Qmdb\Shared\Observability\Logging\LogEventName;
use Qmdb\Shared\Time\Clock;

final readonly class WorkspaceRoleRevocationService
{
    private const string ASSIGN_PERMISSION = 'workspace.authorization.assign';
    private const string PROTECTED_ROLE = 'workspace.owner';

    public function __construct(
        private AuthorizationRequirementGuard $authorization,
        private AuthorizationAdministrationRepository $administration,
        private WorkspaceRoleAssignmentRepository $assignments,
        private DelegationValidator $delegation,
        private StepUpGuard $stepUp,
        private AuthorizationSecurityNotificationService $notifications,
        private SecurityAuditEventAppender $audit,
        private TransactionManager $transactions,
        private EventLogger $logger,
        private Clock $clock,
    ) {
    }

    public function revoke(WorkspaceRoleRevocationCommand $command): bool
    {
        $tenant = $command->tenantContext->tenant();
        $request = new AuthorizationRequest(
            AuthorizationSubject::fromAuthenticatedContext($command->actor),
            new PermissionCode(self::ASSIGN_PERMISSION),
            new WorkspaceAuthorizationScope($command->tenantContext),
        );
        $this->authorization->requireAllowed($request);
        $actorMembership = $this->administration->activeMembershipForAccount(
            $tenant,
            $command->actor->accountInternalId,
        );
        if ($actorMembership === null) {
            return false;
        }
        if ($this->assignments->findActiveAssignment($tenant, $command->assignmentId) === null) {
            return false;
        }
        $now = $this->clock->now();
        $assignment = $this->transactions->transactional(function () use (
            $command,
            $request,
            $actorMembership,
            $now,
            $tenant,
        ) {
            $this->assignments->listActiveForMembership(
                $tenant,
                $actorMembership->internalId,
                100,
                true,
            );
            $this->authorization->requireAllowed($request);
            $locked = $this->assignments->findActiveAssignment(
                $tenant,
                $command->assignmentId,
                true,
            );
            if ($locked === null) {
                return null;
            }
            $role = $this->administration->role($locked->roleCode);
            if ($role === null) {
                throw new \UnexpectedValueException('Assigned workspace role is unavailable.');
            }
            $this->delegation->workspace(
                $command->tenantContext,
                $role,
                true,
            );
            $this->stepUp->consume($command->actor, StepUpAction::AUTHORIZATION_WORKSPACE_ROLE_REVOKE);
            if (
                $locked->roleCode->value() === self::PROTECTED_ROLE
                && $this->assignments->countActiveWorkspaceOwners($tenant, true) <= 1
            ) {
                throw new \DomainException('The final usable workspace owner cannot be revoked.');
            }
            $revoked = $locked->revoke(
                RoleAssignmentActorKind::ACCOUNT,
                $command->actor->accountInternalId,
                $command->reason,
                $now,
            );
            if (!$this->assignments->revoke($tenant, $revoked)) {
                throw new \UnexpectedValueException('Workspace role assignment changed concurrently.');
            }
            $this->notifications->create(
                $revoked->accountInternalId,
                AccountSecurityNotificationType::WORKSPACE_ROLE_REVOKED,
                $revoked->id->toString(),
                $now,
            );
            $this->audit->workspace(
                SecurityEventCode::WORKSPACE_ROLE_REVOKED,
                $command->tenantContext->workspaceId->toString(),
                SecurityEventSubjectKind::ROLE_ASSIGNMENT,
                $revoked->id->toString(),
                $command->actor->accountId->toString(),
                $now,
                [
                    'assignment_public_id' => $revoked->id->toString(),
                    'role_code' => $revoked->roleCode->value(),
                    'scope_type' => AuthorizationScopeType::WORKSPACE->value,
                ],
                $command->reason->value,
                $command->correlationId->value(),
            );

            return $revoked;
        });
        if ($assignment === null) {
            return false;
        }
        $this->logger->log(LogLevel::WARNING, new LogEventName('authorization.workspace.role.revoked'), [
            'actor_account_public_id' => $command->actor->accountId->toString(),
            'assignment_public_id' => $assignment->id->toString(),
            'role_code' => $assignment->roleCode->value(),
            'scope_type' => AuthorizationScopeType::WORKSPACE->value,
            'workspace_public_id' => $command->tenantContext->workspaceId->toString(),
        ]);

        return true;
    }
}
