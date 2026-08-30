<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Application;

use Qmdb\Modules\IdentityMultiFactor\Domain\AccountMfaPolicyStatus;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\AccountMfaPolicyRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\TotpAuthenticatorRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\IdentityMultiFactor\Domain\TotpAuthenticatorStatus;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Time\Clock;

final readonly class TotpAuthenticatorRevocationService
{
    public function __construct(
        private TotpAuthenticatorRepository $authenticators,
        private AccountMfaPolicyRepository $policies,
        private StepUpGuard $stepUp,
        private MultiFactorNotificationService $notifications,
        private TransactionManager $transactions,
        private SecurityAuditEventAppender $audit,
        private Clock $clock,
    ) {
    }

    public function revoke(
        AuthenticatedAccountContext $context,
        string $authenticatorPublicId,
        int $expectedVersion,
    ): void {
        $now = $this->clock->now();
        $this->transactions->transactional(function () use (
            $context,
            $authenticatorPublicId,
            $expectedVersion,
            $now,
        ): void {
            $this->stepUp->consume($context, StepUpAction::MFA_REVOKE_TOTP);
            $authenticator = $this->authenticators->findTotp(
                $context->accountInternalId,
                $authenticatorPublicId,
                true,
            );
            if (
                $authenticator === null || $authenticator->status !== TotpAuthenticatorStatus::ACTIVE
                || $authenticator->version !== $expectedVersion
            ) {
                throw new \DomainException('TOTP authenticator changed or is unavailable.');
            }
            $policy = $this->policies->findPolicy($context->accountInternalId, true);
            if (
                $policy->status === AccountMfaPolicyStatus::ENABLED
                && $this->policies->activeStrongFactorCount($context->accountInternalId) <= 1
            ) {
                throw new \DomainException('Disable MFA before removing the final active authenticator.');
            }
            if (!$this->authenticators->revokeTotp($authenticator, $now)) {
                throw new \UnexpectedValueException('TOTP authenticator revocation failed.');
            }
            $this->notifications->create(
                $context->accountInternalId,
                AccountSecurityNotificationType::TOTP_AUTHENTICATOR_REMOVED,
                $authenticator->publicId,
                $now,
            );
            $this->audit->account(SecurityEventCode::TOTP_REMOVED, $context->accountId->toString(), $context->accountId->toString(), $context->sessionId->toString(), $now, ['authenticator_public_id' => $authenticator->publicId]);
        });
    }
}
