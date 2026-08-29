<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace Qmdb\Modules\SecurityPrivilegedAccess\Application;

use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequest;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationSubject;
use Qmdb\Modules\SecurityAuthorization\Application\BaseRoleAuthorizationGuard;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationScopeType;
use Qmdb\Modules\SecurityAuthorization\Domain\PlatformAuthorizationScope;
use Qmdb\Modules\SecurityAuthorization\Domain\WorkspaceAuthorizationScope;
use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitAttempt;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitPolicy;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitScope;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType;
use Qmdb\Modules\SecurityPrivilegedAccess\Configuration\PrivilegedAccessConfiguration;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessRequestId;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessType;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Time\Clock;

final readonly class PrivilegedAccessRequestService
{
    public function __construct(
        private BaseRoleAuthorizationGuard $baseAuthorization,
        private PrivilegedAccessRequestRepository $requests,
        private PrivilegedAccessConfiguration $configuration,
        private IdentityRateLimiter $rateLimiter,
        private IdentityFingerprintGenerator $fingerprints,
        private PrivilegedAccessNotificationService $notifications,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {
    }

    public function request(
        PrivilegedAccessRequestCommand $command,
        ?WorkspaceAuthorizationScope $workspaceScope = null,
    ): PrivilegedAccessRequestId {
        $this->assertCommand($command, $workspaceScope);
        $permission = match ($command->type) {
            PrivilegedAccessType::TEMPORARY_PRIVILEGE => $command->scope === AuthorizationScopeType::PLATFORM
                ? 'platform.temporary_privileges.request' : 'workspace.temporary_privileges.request',
            PrivilegedAccessType::SUPPORT_ACCESS => 'platform.support_access.request',
            PrivilegedAccessType::BREAK_GLASS => throw new \LogicException('Break-glass access was not rejected before authorization.'),
        };
        $scope = $command->scope === AuthorizationScopeType::WORKSPACE && $command->type === PrivilegedAccessType::TEMPORARY_PRIVILEGE
            ? $workspaceScope : new PlatformAuthorizationScope();
        if ($scope === null) {
            throw new \DomainException('A current workspace context is required.');
        }
        $this->baseAuthorization->requireAllowed(new AuthorizationRequest(
            AuthorizationSubject::fromAuthenticatedContext($command->actor),
            new \Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode($permission),
            $scope,
        ));
        $now = $this->clock->now();
        $decision = $this->rateLimiter->consume($this->rateAttempts($command), $now);
        if (!$decision->allowed) {
            throw new \DomainException('Privileged-access requests are temporarily rate limited.');
        }

        return $this->transactions->transactional(function () use ($command, $now): PrivilegedAccessRequestId {
            $requestId = $this->requests->create(
                $command,
                $now,
                $now->modify('+' . $this->configuration->requestTtlSeconds . ' seconds'),
            );
            $this->notifications->create(
                $command->actor->accountInternalId,
                $command->type === PrivilegedAccessType::SUPPORT_ACCESS
                    ? AccountSecurityNotificationType::SUPPORT_ACCESS_REQUESTED
                    : AccountSecurityNotificationType::TEMPORARY_PRIVILEGE_REQUESTED,
                $requestId->toString(),
                $now,
            );

            return $requestId;
        });
    }

    private function assertCommand(PrivilegedAccessRequestCommand $command, ?WorkspaceAuthorizationScope $workspaceScope): void
    {
        if ($command->type === PrivilegedAccessType::BREAK_GLASS) {
            throw new \DomainException('Break-glass access must use the atomic break-glass activation workflow.');
        }
        if (count($command->permissions) > $this->configuration->maximumPermissions) {
            throw new \DomainException('The requested permission count exceeds the privileged-access limit.');
        }
        $codes = array_map(static fn (\Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode $permission): string =>
            $permission->value(), $command->permissions);
        if (count($codes) !== count(array_unique($codes))) {
            throw new \DomainException('A privileged-access request cannot duplicate a permission.');
        }
        $maximumDuration = match ($command->type) {
            PrivilegedAccessType::TEMPORARY_PRIVILEGE => $this->configuration->temporaryPrivilegeMaximumTtlSeconds,
            PrivilegedAccessType::SUPPORT_ACCESS => $this->configuration->supportAccessMaximumTtlSeconds,
        };
        if ($command->duration->seconds > $maximumDuration) {
            throw new \DomainException('The requested privileged-access duration exceeds the access-type maximum.');
        }
        if ($command->type === PrivilegedAccessType::SUPPORT_ACCESS && $command->scope !== AuthorizationScopeType::WORKSPACE) {
            throw new \DomainException('Support access is limited to one workspace.');
        }
        if ($command->scope === AuthorizationScopeType::WORKSPACE && $command->workspaceInternalId === null) {
            throw new \DomainException('Workspace privileged access requires an exact workspace.');
        }
        if (
            $command->type === PrivilegedAccessType::TEMPORARY_PRIVILEGE && $command->scope === AuthorizationScopeType::WORKSPACE
            && ($workspaceScope === null || $workspaceScope->tenantContext->workspaceInternalId !== $command->workspaceInternalId
                || $command->subjectMembershipInternalId !== $workspaceScope->tenantContext->membershipInternalId())
        ) {
            throw new \DomainException('Temporary workspace access must use the actor’s current active membership.');
        }
        if ($command->type === PrivilegedAccessType::SUPPORT_ACCESS && $command->reference === null) {
            throw new \DomainException('Support access requires an incident or support reference.');
        }
    }

    /** @return non-empty-list<IdentityRateLimitAttempt> */
    private function rateAttempts(PrivilegedAccessRequestCommand $command): array
    {
        $policy = new IdentityRateLimitPolicy(
            $this->configuration->requestWindowSeconds,
            $this->configuration->requestMaximumAttempts,
            $this->configuration->requestWindowSeconds,
        );

        return [
            new IdentityRateLimitAttempt(
                IdentityRateLimitScope::PRIVILEGED_ACCESS_REQUEST_ACCOUNT,
                $this->fingerprints->generate('privileged-access-account', (string) $command->actor->accountInternalId),
                $policy,
            ),
            new IdentityRateLimitAttempt(
                IdentityRateLimitScope::PRIVILEGED_ACCESS_REQUEST_PEER,
                $this->fingerprints->generate('privileged-access-peer', (string) $command->actor->sessionInternalId),
                $policy,
            ),
        ];
    }
}
