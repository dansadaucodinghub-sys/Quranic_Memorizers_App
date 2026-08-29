<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace Qmdb\Modules\SecurityPrivilegedAccess\Application;

use Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequest;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationSubject;
use Qmdb\Modules\SecurityAuthorization\Application\BaseRoleAuthorizationGuard;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\PlatformAuthorizationScope;
use Qmdb\Modules\SecurityAuthorization\Domain\WorkspaceAuthorizationScope;
use Qmdb\Modules\SecurityPrivilegedAccess\Configuration\PrivilegedAccessConfiguration;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessApprovalDecision;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessApprovalType;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessType;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Time\Clock;

/** Approves or rejects a request using only ordinary, base-role authorization. */
final readonly class PrivilegedAccessApprovalService
{
    public function __construct(
        private BaseRoleAuthorizationGuard $authorization,
        private PrivilegedAccessLifecycleRepository $lifecycle,
        private StepUpGuard $stepUp,
        private PrivilegedAccessConfiguration $configuration,
        private PrivilegedAccessNotificationService $notifications,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {
    }

    public function decide(
        PrivilegedAccessApprovalCommand $command,
        ?WorkspaceAuthorizationScope $workspaceScope = null,
    ): PrivilegedAccessApprovalResult {
        $request = $this->lifecycle->requestSnapshot($command->requestId);
        if ($request === null) {
            throw new \DomainException('The privileged-access request is unavailable.');
        }
        $permission = $this->permission($request->type, $command->approvalType);
        $scope = $command->approvalType === PrivilegedAccessApprovalType::PLATFORM
            ? new PlatformAuthorizationScope()
            : $workspaceScope;
        if (
            $scope === null || ($scope instanceof WorkspaceAuthorizationScope
            && ($scope->tenantContext->workspaceInternalId !== $request->workspaceInternalId
                || $scope->tenantContext->membershipInternalId() !== $command->approverMembershipInternalId))
        ) {
            throw new \DomainException('A current active workspace membership is required for this approval.');
        }
        $this->authorization->requireAllowed(new AuthorizationRequest(
            AuthorizationSubject::fromAuthenticatedContext($command->actor),
            new PermissionCode($permission),
            $scope,
        ));
        $action = $this->action($request->type, $command->approvalType);
        $requiredAssurance = $this->requiredAssurance($request->type, $command->approvalType);
        if (!$command->actor->assurance->level->satisfies($requiredAssurance)) {
            throw new \DomainException('The current session assurance is insufficient for this approval.');
        }
        $maximum = match ($request->type) {
            PrivilegedAccessType::TEMPORARY_PRIVILEGE => $this->configuration->temporaryPrivilegeMaximumTtlSeconds,
            PrivilegedAccessType::SUPPORT_ACCESS => $this->configuration->supportAccessMaximumTtlSeconds,
            PrivilegedAccessType::BREAK_GLASS => throw new \DomainException('Break-glass requests do not support approval.'),
        };
        if ($command->approvedDuration->seconds > $maximum) {
            throw new \DomainException('The privileged-access approval duration exceeds its configured maximum.');
        }

        return $this->transactions->transactional(function () use ($command, $scope, $permission, $action): PrivilegedAccessApprovalResult {
            $this->authorization->requireAllowed(new AuthorizationRequest(
                AuthorizationSubject::fromAuthenticatedContext($command->actor),
                new PermissionCode($permission),
                $scope,
            ));
            $grant = $this->stepUp->consumeWithGrant($command->actor, $action);

            $now = $this->clock->now();
            $result = $this->lifecycle->approve($command, $grant->internalId, $now);
            $updatedRequest = $this->lifecycle->requestSnapshot($command->requestId);
            if ($updatedRequest === null) {
                throw new \UnexpectedValueException('Approved privileged-access request is unavailable.');
            }
            $this->notifications->create(
                $updatedRequest->subjectAccountInternalId,
                $this->notificationType($command->requestId, $result),
                $result->requestId->toString(),
                $now,
            );

            return $result;
        });
    }

    private function permission(PrivilegedAccessType $type, PrivilegedAccessApprovalType $approval): string
    {
        return match ($approval) {
            PrivilegedAccessApprovalType::PLATFORM => match ($type) {
                PrivilegedAccessType::TEMPORARY_PRIVILEGE => 'platform.temporary_privileges.approve',
                PrivilegedAccessType::SUPPORT_ACCESS => 'platform.support_access.approve',
                PrivilegedAccessType::BREAK_GLASS => throw new \DomainException('Break-glass requests do not support approval.'),
            },
            PrivilegedAccessApprovalType::WORKSPACE => match ($type) {
                PrivilegedAccessType::TEMPORARY_PRIVILEGE => 'workspace.temporary_privileges.approve',
                PrivilegedAccessType::SUPPORT_ACCESS => 'workspace.support_access.approve',
                PrivilegedAccessType::BREAK_GLASS => throw new \DomainException('Break-glass requests do not support approval.'),
            },
        };
    }

    private function action(PrivilegedAccessType $type, PrivilegedAccessApprovalType $approval): StepUpAction
    {
        return match ($type) {
            PrivilegedAccessType::TEMPORARY_PRIVILEGE => StepUpAction::TEMPORARY_PRIVILEGE_APPROVE,
            PrivilegedAccessType::SUPPORT_ACCESS => $approval === PrivilegedAccessApprovalType::PLATFORM
                ? StepUpAction::SUPPORT_ACCESS_PLATFORM_APPROVE
                : StepUpAction::SUPPORT_ACCESS_WORKSPACE_APPROVE,
            PrivilegedAccessType::BREAK_GLASS => throw new \DomainException('Break-glass requests do not support approval.'),
        };
    }

    private function requiredAssurance(PrivilegedAccessType $type, PrivilegedAccessApprovalType $approval): AuthenticationAssuranceLevel
    {
        if ($type === PrivilegedAccessType::SUPPORT_ACCESS) {
            return AuthenticationAssuranceLevel::PHISHING_RESISTANT;
        }

        return $approval === PrivilegedAccessApprovalType::PLATFORM
            ? AuthenticationAssuranceLevel::PHISHING_RESISTANT
            : AuthenticationAssuranceLevel::MULTI_FACTOR;
    }

    private function notificationType(\Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessRequestId $requestId, PrivilegedAccessApprovalResult $result): AccountSecurityNotificationType
    {
        $request = $this->lifecycle->requestSnapshot($requestId);
        if ($request === null) {
            throw new \UnexpectedValueException('Privileged-access request is unavailable for notification.');
        }
        if ($request->type === PrivilegedAccessType::TEMPORARY_PRIVILEGE) {
            return $result->decision === PrivilegedAccessApprovalDecision::REJECTED
                ? AccountSecurityNotificationType::TEMPORARY_PRIVILEGE_REJECTED
                : AccountSecurityNotificationType::TEMPORARY_PRIVILEGE_APPROVED;
        }

        return match ($result->requestStatus) {
            \Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessRequestStatus::PARTIALLY_APPROVED => AccountSecurityNotificationType::SUPPORT_ACCESS_PARTIALLY_APPROVED,
            \Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessRequestStatus::APPROVED => AccountSecurityNotificationType::SUPPORT_ACCESS_APPROVED,
            default => AccountSecurityNotificationType::SUPPORT_ACCESS_REQUESTED,
        };
    }
}
