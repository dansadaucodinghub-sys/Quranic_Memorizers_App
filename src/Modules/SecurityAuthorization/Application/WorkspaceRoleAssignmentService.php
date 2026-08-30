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
use Qmdb\Modules\SecurityAuthorization\Domain\RoleAssignmentStatus;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleAssignmentVersion;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleStatus;
use Qmdb\Modules\SecurityAuthorization\Domain\WorkspaceAuthorizationScope;
use Qmdb\Modules\SecurityAuthorization\Domain\WorkspaceRoleAssignment;
use Qmdb\Modules\SecurityAuthorization\Domain\WorkspaceRoleAssignmentId;
use Qmdb\Shared\Configuration\Logging\LogLevel;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Observability\Logging\EventLogger;
use Qmdb\Shared\Observability\Logging\LogEventName;
use Qmdb\Shared\Time\Clock;

final readonly class WorkspaceRoleAssignmentService
{
    private const string ASSIGN_PERMISSION = 'workspace.authorization.assign';

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

    public function assign(WorkspaceRoleAssignmentCommand $command): WorkspaceRoleAssignmentResult
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
        $target = $this->administration->membership($tenant, $command->targetMembershipId);
        $role = $this->administration->role($command->roleCode);
        if (
            $actorMembership === null || $target === null || !$target->active || $role === null
            || $role->scopeType !== AuthorizationScopeType::WORKSPACE
            || $role->status !== RoleStatus::ACTIVE
        ) {
            throw new \DomainException('The target membership or workspace role is not eligible.');
        }
        $this->delegation->workspace($command->tenantContext, $role);
        $now = $this->clock->now();
        $result = $this->transactions->transactional(function () use (
            $command,
            $request,
            $actorMembership,
            $target,
            $role,
            $now,
            $tenant,
        ): WorkspaceRoleAssignmentResult {
            $this->assignments->listActiveForMembership(
                $tenant,
                $actorMembership->internalId,
                100,
                true,
            );
            $this->authorization->requireAllowed($request);
            $this->delegation->workspace(
                $command->tenantContext,
                $role,
                true,
            );
            $this->stepUp->consume($command->actor, StepUpAction::AUTHORIZATION_WORKSPACE_ROLE_ASSIGN);
            if (
                $this->assignments->findActiveForMembershipAndRole(
                    $tenant,
                    $target->internalId,
                    $role->internalId,
                    true,
                ) !== null
            ) {
                throw new \DomainException('An active workspace role assignment already exists.');
            }
            $assignment = new WorkspaceRoleAssignment(
                null,
                WorkspaceRoleAssignmentId::generate(),
                $command->tenantContext->workspaceInternalId,
                $target->internalId,
                $target->accountInternalId,
                $role->internalId,
                $role->code,
                RoleAssignmentStatus::ACTIVE,
                new RoleAssignmentVersion(1),
                RoleAssignmentActorKind::ACCOUNT,
                $command->actor->accountInternalId,
                $command->reason,
                $now,
                null,
                null,
                null,
                null,
                $command->correlationId->value(),
                $now,
                $now,
            );
            $this->assignments->add($tenant, $assignment);
            $this->notifications->create(
                $target->accountInternalId,
                AccountSecurityNotificationType::WORKSPACE_ROLE_ASSIGNED,
                $assignment->id->toString(),
                $now,
            );
            $this->audit->workspace(
                SecurityEventCode::WORKSPACE_ROLE_ASSIGNED,
                $command->tenantContext->workspaceId->toString(),
                SecurityEventSubjectKind::ROLE_ASSIGNMENT,
                $assignment->id->toString(),
                $command->actor->accountId->toString(),
                $now,
                [
                    'assignment_public_id' => $assignment->id->toString(),
                    'role_code' => $assignment->roleCode->value(),
                    'scope_type' => AuthorizationScopeType::WORKSPACE->value,
                ],
                $command->reason->value,
                $command->correlationId->value(),
            );

            return new WorkspaceRoleAssignmentResult(
                $assignment->id,
                $assignment->roleCode,
                $assignment->status,
                $assignment->version,
                $command->tenantContext->workspaceId,
            );
        });
        $this->logger->log(LogLevel::NOTICE, new LogEventName('authorization.workspace.role.assigned'), [
            'actor_account_public_id' => $command->actor->accountId->toString(),
            'target_account_public_id' => $target->accountId->toString(),
            'assignment_public_id' => $result->assignmentId->toString(),
            'role_code' => $result->roleCode->value(),
            'scope_type' => AuthorizationScopeType::WORKSPACE->value,
            'workspace_public_id' => $result->workspaceId->toString(),
        ]);

        return $result;
    }
}
