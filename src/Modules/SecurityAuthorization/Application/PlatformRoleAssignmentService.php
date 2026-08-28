<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Application;

use Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationScopeType;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\PlatformAuthorizationScope;
use Qmdb\Modules\SecurityAuthorization\Domain\PlatformRoleAssignment;
use Qmdb\Modules\SecurityAuthorization\Domain\PlatformRoleAssignmentId;
use Qmdb\Modules\SecurityAuthorization\Domain\Repository\AuthorizationAdministrationRepository;
use Qmdb\Modules\SecurityAuthorization\Domain\Repository\PlatformRoleAssignmentRepository;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleAssignmentActorKind;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleAssignmentStatus;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleAssignmentVersion;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleStatus;
use Qmdb\Shared\Configuration\Logging\LogLevel;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Observability\Logging\EventLogger;
use Qmdb\Shared\Observability\Logging\LogEventName;
use Qmdb\Shared\Time\Clock;

final readonly class PlatformRoleAssignmentService
{
    private const string ASSIGN_PERMISSION = 'platform.authorization.assign';

    public function __construct(
        private AuthorizationGuard $authorization,
        private AuthorizationAdministrationRepository $administration,
        private PlatformRoleAssignmentRepository $assignments,
        private DelegationValidator $delegation,
        private StepUpGuard $stepUp,
        private AuthorizationSecurityNotificationService $notifications,
        private TransactionManager $transactions,
        private EventLogger $logger,
        private Clock $clock,
    ) {
    }

    public function assign(PlatformRoleAssignmentCommand $command): PlatformRoleAssignmentResult
    {
        $request = new AuthorizationRequest(
            AuthorizationSubject::fromAuthenticatedContext($command->actor),
            new PermissionCode(self::ASSIGN_PERMISSION),
            new PlatformAuthorizationScope(),
        );
        $this->authorization->requireAllowed($request);
        $target = $this->administration->account($command->targetAccountId);
        $role = $this->administration->role($command->roleCode);
        if (
            $target === null || !$target->active || $role === null
            || $role->scopeType !== AuthorizationScopeType::PLATFORM
            || $role->status !== RoleStatus::ACTIVE
        ) {
            throw new \DomainException('The target account or platform role is not eligible.');
        }
        $this->delegation->platform($command->actor->accountInternalId, $role);
        $now = $this->clock->now();
        $result = $this->transactions->transactional(function () use (
            $command,
            $request,
            $target,
            $role,
            $now,
        ): PlatformRoleAssignmentResult {
            $this->assignments->listActiveForAccount($command->actor->accountInternalId, 100, true);
            $this->authorization->requireAllowed($request);
            $this->delegation->platform($command->actor->accountInternalId, $role, true);
            $this->stepUp->consume($command->actor, StepUpAction::AUTHORIZATION_PLATFORM_ROLE_ASSIGN);
            if (
                $this->assignments->findActiveForAccountAndRole(
                    $target->internalId,
                    $role->internalId,
                    true,
                ) !== null
            ) {
                throw new \DomainException('An active platform role assignment already exists.');
            }
            $assignment = new PlatformRoleAssignment(
                null,
                PlatformRoleAssignmentId::generate(),
                $target->internalId,
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
            $this->assignments->add($assignment);
            $this->notifications->create(
                $target->internalId,
                AccountSecurityNotificationType::PLATFORM_ROLE_ASSIGNED,
                $assignment->id->toString(),
                $now,
            );

            return new PlatformRoleAssignmentResult(
                $assignment->id,
                $assignment->roleCode,
                $assignment->status,
                $assignment->version,
            );
        });
        $this->logger->log(LogLevel::NOTICE, new LogEventName('authorization.platform.role.assigned'), [
            'actor_account_public_id' => $command->actor->accountId->toString(),
            'target_account_public_id' => $target->id->toString(),
            'assignment_public_id' => $result->assignmentId->toString(),
            'role_code' => $result->roleCode->value(),
            'scope_type' => AuthorizationScopeType::PLATFORM->value,
        ]);

        return $result;
    }
}
