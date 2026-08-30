<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Application;

use Qmdb\Modules\IdentityMultiFactor\Domain\AccountMfaPolicyStatus;
use Qmdb\Modules\IdentityMultiFactor\Domain\PasskeyCredentialStatus;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\AccountMfaPolicyRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\PasskeyCredentialRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Time\Clock;

final readonly class PasskeyRevocationService
{
    public function __construct(
        private PasskeyCredentialRepository $passkeys,
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
        string $passkeyPublicId,
        int $expectedVersion,
    ): void {
        $now = $this->clock->now();
        $this->transactions->transactional(function () use (
            $context,
            $passkeyPublicId,
            $expectedVersion,
            $now,
        ): void {
            $this->stepUp->consume($context, StepUpAction::MFA_REVOKE_PASSKEY);
            $passkey = $this->passkeys->findPasskey($context->accountInternalId, $passkeyPublicId, true);
            if (
                $passkey === null || $passkey->status !== PasskeyCredentialStatus::ACTIVE
                || $passkey->version !== $expectedVersion
            ) {
                throw new \DomainException('Passkey changed or is unavailable.');
            }
            $policy = $this->policies->findPolicy($context->accountInternalId, true);
            if (
                $policy->status === AccountMfaPolicyStatus::ENABLED
                && $this->policies->activeStrongFactorCount($context->accountInternalId) <= 1
            ) {
                throw new \DomainException('Disable MFA before removing the final active authenticator.');
            }
            if (!$this->passkeys->revokePasskey($passkey, $now)) {
                throw new \UnexpectedValueException('Passkey revocation failed.');
            }
            $this->notifications->create(
                $context->accountInternalId,
                AccountSecurityNotificationType::PASSKEY_REMOVED,
                $passkey->publicId,
                $now,
            );
            $this->audit->account(SecurityEventCode::PASSKEY_REMOVED, $context->accountId->toString(), $context->accountId->toString(), $context->sessionId->toString(), $now, ['authenticator_public_id' => $passkey->publicId]);
        });
    }
}
