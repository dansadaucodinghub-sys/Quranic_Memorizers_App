<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace Qmdb\Modules\SecurityPrivilegedAccess\Application;

use Qmdb\Modules\IdentityAccess\Security\Fingerprint\IdentityFingerprintGenerator;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitAttempt;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitPolicy;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimitScope;
use Qmdb\Modules\IdentityAccess\Security\RateLimit\IdentityRateLimiter;
use Qmdb\Modules\IdentityMultiFactor\Application\StepUpGuard;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationRequest;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationSubject;
use Qmdb\Modules\SecurityAuthorization\Application\BaseRoleAuthorizationGuard;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\PlatformAuthorizationScope;
use Qmdb\Modules\SecurityPrivilegedAccess\Configuration\PrivilegedAccessConfiguration;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessType;
use Qmdb\Modules\TenancyContext\Domain\Repository\SessionTenantContextRepository;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Time\Clock;

/** Creates and activates break-glass access atomically; there is no pending emergency request. */
final readonly class BreakGlassActivationService
{
    public function __construct(
        private BaseRoleAuthorizationGuard $authorization,
        private PrivilegedAccessRequestRepository $requests,
        private PrivilegedAccessActivationRepository $activations,
        private PrivilegedAccessLifecycleRepository $lifecycle,
        private SessionTenantContextRepository $tenantContexts,
        private StepUpGuard $stepUp,
        private IdentityRateLimiter $rateLimiter,
        private IdentityFingerprintGenerator $fingerprints,
        private PrivilegedAccessNotificationService $notifications,
        private PrivilegedAccessConfiguration $configuration,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {
    }

    public function activate(BreakGlassActivationCommand $command): BreakGlassActivationResult
    {
        $request = $command->request;
        $this->authorization->requireAllowed(new AuthorizationRequest(
            AuthorizationSubject::fromAuthenticatedContext($request->actor),
            new PermissionCode('platform.break_glass.activate'),
            new PlatformAuthorizationScope(),
        ));
        if (
            !$request->actor->assurance->level->satisfies(AuthenticationAssuranceLevel::PHISHING_RESISTANT)
            || $request->reference === null
            || count($request->permissions) > $this->configuration->maximumPermissions
            || $request->duration->seconds > $this->configuration->breakGlassMaximumTtlSeconds
        ) {
            throw new \DomainException('The break-glass request is not eligible for activation.');
        }
        $rate = $this->rateLimiter->consume($this->rateAttempts($command), $this->clock->now());
        if (!$rate->allowed) {
            throw new \DomainException('Break-glass activation is temporarily rate limited.');
        }
        if ($this->lifecycle->hasOverdueReviewForSubject($request->actor->accountInternalId, PrivilegedAccessType::BREAK_GLASS)) {
            throw new \DomainException('An overdue break-glass review must be completed before another emergency activation.');
        }

        return $this->transactions->transactional(function () use ($request): BreakGlassActivationResult {
            $now = $this->clock->now();
            $this->authorization->requireAllowed(new AuthorizationRequest(
                AuthorizationSubject::fromAuthenticatedContext($request->actor),
                new PermissionCode('platform.break_glass.activate'),
                new PlatformAuthorizationScope(),
            ));
            $this->stepUp->consume($request->actor, StepUpAction::BREAK_GLASS_ACTIVATE);
            $state = $this->tenantContexts->state($request->actor, true);
            if (!$this->tenantContexts->clear($request->actor, $state->version, $now)) {
                throw new \DomainException('The selected workspace context changed; retry from the current session state.');
            }
            $requestId = $this->requests->create(
                $request,
                $now,
                $now->modify('+' . $this->configuration->requestTtlSeconds . ' seconds'),
            );
            $activation = $this->activations->activate(
                $request->actor,
                $requestId,
                PrivilegedAccessType::BREAK_GLASS,
                $state->version->value + 1,
                $request->correlationId->value(),
                $now,
                $now->modify('+' . $this->configuration->reviewTtlSeconds . ' seconds'),
            );
            $this->notifications->create(
                $request->actor->accountInternalId,
                AccountSecurityNotificationType::BREAK_GLASS_ACTIVATED,
                $activation->toString(),
                $now,
            );

            return new BreakGlassActivationResult($requestId, $activation);
        });
    }

    /** @return non-empty-list<IdentityRateLimitAttempt> */
    private function rateAttempts(BreakGlassActivationCommand $command): array
    {
        $actor = $command->request->actor;
        $policy = new IdentityRateLimitPolicy(
            $this->configuration->breakGlassWindowSeconds,
            $this->configuration->breakGlassMaximumAttempts,
            $this->configuration->breakGlassWindowSeconds,
        );

        return [
            new IdentityRateLimitAttempt(
                IdentityRateLimitScope::BREAK_GLASS_ACTIVATION_ACCOUNT,
                $this->fingerprints->generate('break-glass-account', (string) $actor->accountInternalId),
                $policy
            ),
            new IdentityRateLimitAttempt(
                IdentityRateLimitScope::BREAK_GLASS_ACTIVATION_PEER,
                $this->fingerprints->generate('break-glass-peer', (string) $actor->sessionInternalId),
                $policy
            ),
        ];
    }
}
