<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Application;

use Qmdb\Modules\IdentityMultiFactor\Domain\AccountMfaPolicyStatus;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\AccountMfaPolicyRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\RecoveryCodeSetRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\IdentitySessions\Domain\Repository\UserSessionRepository;
use Qmdb\Modules\IdentitySessions\Domain\SessionRevocationReason;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Time\Clock;

final readonly class AccountMfaDisablementService
{
    public function __construct(
        private AccountMfaPolicyRepository $policies,
        private RecoveryCodeSetRepository $recoveryCodes,
        private StepUpGuard $stepUp,
        private UserSessionRepository $sessions,
        private MultiFactorNotificationService $notifications,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {
    }

    public function disable(AuthenticatedAccountContext $context): void
    {
        $now = $this->clock->now();
        $this->transactions->transactional(function () use ($context, $now): void {
            $this->stepUp->consume($context, StepUpAction::MFA_DISABLE);
            $policy = $this->policies->findPolicy($context->accountInternalId, true);
            if ($policy->status !== AccountMfaPolicyStatus::ENABLED) {
                throw new \DomainException('Multi-factor authentication is not enabled.');
            }
            if (!$this->policies->disablePolicy($policy, $now)) {
                throw new \UnexpectedValueException('MFA policy disablement failed.');
            }
            $this->recoveryCodes->revokeActiveRecoveryCodeSet($context->accountInternalId, $now);
            $this->sessions->revokeOthersForAccount(
                $context->accountInternalId,
                $context->sessionInternalId,
                SessionRevocationReason::MFA_POLICY_CHANGED,
                $now,
            );
            $this->notifications->create(
                $context->accountInternalId,
                AccountSecurityNotificationType::MFA_DISABLED,
                UuidV7::generate()->toString(),
                $now,
            );
        });
    }
}
