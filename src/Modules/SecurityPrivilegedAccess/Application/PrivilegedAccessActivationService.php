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
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\PlatformAuthorizationScope;
use Qmdb\Modules\SecurityPrivilegedAccess\Configuration\PrivilegedAccessConfiguration;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessActivationId;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessRequestId;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessType;
use Qmdb\Modules\TenancyContext\Domain\Repository\SessionTenantContextRepository;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Observability\Correlation\CorrelationId;
use Qmdb\Shared\Time\Clock;

final readonly class PrivilegedAccessActivationService
{
    public function __construct(
        private PrivilegedAccessActivationRepository $activations,
        private SessionTenantContextRepository $tenantContexts,
        private BaseRoleAuthorizationGuard $baseAuthorization,
        private PrivilegedAccessLifecycleRepository $lifecycle,
        private StepUpGuard $stepUp,
        private PrivilegedAccessConfiguration $configuration,
        private PrivilegedAccessNotificationService $notifications,
        private SecurityAuditEventAppender $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {
    }

    public function activate(
        AuthenticatedAccountContext $actor,
        PrivilegedAccessRequestId $requestId,
        PrivilegedAccessType $type,
        CorrelationId $correlationId,
    ): PrivilegedAccessActivationId {
        if ($type === PrivilegedAccessType::BREAK_GLASS) {
            throw new \DomainException('Break-glass access must use the atomic break-glass activation workflow.');
        }
        $action = match ($type) {
            PrivilegedAccessType::TEMPORARY_PRIVILEGE => StepUpAction::TEMPORARY_PRIVILEGE_ACTIVATE,
            PrivilegedAccessType::SUPPORT_ACCESS => StepUpAction::SUPPORT_ACCESS_ACTIVATE,
        };
        if ($type === PrivilegedAccessType::SUPPORT_ACCESS) {
            $this->baseAuthorization->requireAllowed(new AuthorizationRequest(
                AuthorizationSubject::fromAuthenticatedContext($actor),
                new PermissionCode('platform.support_access.activate'),
                new PlatformAuthorizationScope(),
            ));
            if ($this->lifecycle->hasOverdueReviewForSubject($actor->accountInternalId, PrivilegedAccessType::SUPPORT_ACCESS)) {
                throw new \DomainException('An overdue support-access review must be completed before another support activation.');
            }
        }
        $now = $this->clock->now();

        return $this->transactions->transactional(function () use ($actor, $requestId, $type, $action, $correlationId, $now): PrivilegedAccessActivationId {
            $auditRequest = $this->lifecycle->requestSnapshot($requestId);
            if ($auditRequest === null) {
                throw new \UnexpectedValueException('Activated privileged-access request is unavailable for audit routing.');
            }
            $state = $this->tenantContexts->state($actor, true);
            if (!$this->tenantContexts->clear($actor, $state->version, $now)) {
                throw new \DomainException('The selected workspace context changed; retry from current session state.');
            }
            $this->stepUp->consume($actor, $action);

            $activation = $this->activations->activate(
                $actor,
                $requestId,
                $type,
                $state->version->value + 1,
                $correlationId->value(),
                $now,
                $type === PrivilegedAccessType::SUPPORT_ACCESS
                    ? $now->modify('+' . $this->configuration->reviewTtlSeconds . ' seconds')
                    : null,
            );
            $this->notifications->create(
                $actor->accountInternalId,
                match ($type) {
                    PrivilegedAccessType::TEMPORARY_PRIVILEGE => AccountSecurityNotificationType::TEMPORARY_PRIVILEGE_ACTIVATED,
                    PrivilegedAccessType::SUPPORT_ACCESS => AccountSecurityNotificationType::SUPPORT_ACCESS_ACTIVATED,
                },
                $activation->toString(),
                $now,
            );
            $this->audit->privilegedAccess(
                $type === PrivilegedAccessType::TEMPORARY_PRIVILEGE
                    ? SecurityEventCode::TEMPORARY_ACTIVATED : SecurityEventCode::SUPPORT_ACTIVATED,
                $auditRequest->scope->value === 'WORKSPACE',
                $auditRequest->workspacePublicId,
                SecurityEventSubjectKind::PRIVILEGED_ACCESS,
                $activation->toString(),
                $actor->accountId->toString(),
                $now,
                ['access_type' => $type->value],
                null,
                $correlationId->value(),
            );

            return $activation;
        });
    }
}
