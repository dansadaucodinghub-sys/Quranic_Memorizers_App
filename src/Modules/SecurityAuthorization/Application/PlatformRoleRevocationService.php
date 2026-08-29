<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Application;

use Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationScopeType;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\PlatformAuthorizationScope;
use Qmdb\Modules\SecurityAuthorization\Domain\Repository\AuthorizationAdministrationRepository;
use Qmdb\Modules\SecurityAuthorization\Domain\Repository\PlatformRoleAssignmentRepository;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleAssignmentActorKind;
use Qmdb\Shared\Configuration\Logging\LogLevel;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Observability\Logging\EventLogger;
use Qmdb\Shared\Observability\Logging\LogEventName;
use Qmdb\Shared\Time\Clock;

final readonly class PlatformRoleRevocationService
{
    private const string ASSIGN_PERMISSION = 'platform.authorization.assign';
    private const string PROTECTED_ROLE = 'platform.security_administrator';

    public function __construct(
        private AuthorizationRequirementGuard $authorization,
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

    public function revoke(PlatformRoleRevocationCommand $command): bool
    {
        $request = new AuthorizationRequest(
            AuthorizationSubject::fromAuthenticatedContext($command->actor),
            new PermissionCode(self::ASSIGN_PERMISSION),
            new PlatformAuthorizationScope(),
        );
        $this->authorization->requireAllowed($request);
        if ($this->assignments->findActiveAssignment($command->targetAccountId, $command->assignmentId) === null) {
            return false;
        }
        $now = $this->clock->now();
        $assignment = $this->transactions->transactional(function () use ($command, $request, $now) {
            $this->assignments->listActiveForAccount($command->actor->accountInternalId, 100, true);
            $this->authorization->requireAllowed($request);
            $locked = $this->assignments->findActiveAssignment(
                $command->targetAccountId,
                $command->assignmentId,
                true,
            );
            if ($locked === null) {
                return null;
            }
            $role = $this->administration->role($locked->roleCode);
            if ($role === null) {
                throw new \UnexpectedValueException('Assigned platform role is unavailable.');
            }
            $this->delegation->platform($command->actor->accountInternalId, $role, true);
            $this->stepUp->consume($command->actor, StepUpAction::AUTHORIZATION_PLATFORM_ROLE_REVOKE);
            if (
                $locked->roleCode->value() === self::PROTECTED_ROLE
                && $this->assignments->countActivePlatformSecurityAdministrators(true) <= 1
            ) {
                throw new \DomainException('The final usable platform security administrator cannot be revoked.');
            }
            $revoked = $locked->revoke(
                RoleAssignmentActorKind::ACCOUNT,
                $command->actor->accountInternalId,
                $command->reason,
                $now,
            );
            if (!$this->assignments->revoke($revoked)) {
                throw new \UnexpectedValueException('Platform role assignment changed concurrently.');
            }
            $this->notifications->create(
                $revoked->accountInternalId,
                AccountSecurityNotificationType::PLATFORM_ROLE_REVOKED,
                $revoked->id->toString(),
                $now,
            );

            return $revoked;
        });
        if ($assignment === null) {
            return false;
        }
        $this->logger->log(LogLevel::WARNING, new LogEventName('authorization.platform.role.revoked'), [
            'actor_account_public_id' => $command->actor->accountId->toString(),
            'target_account_public_id' => $command->targetAccountId->toString(),
            'assignment_public_id' => $assignment->id->toString(),
            'role_code' => $assignment->roleCode->value(),
            'scope_type' => AuthorizationScopeType::PLATFORM->value,
        ]);

        return true;
    }
}
