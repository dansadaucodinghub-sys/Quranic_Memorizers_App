<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Application;

use Qmdb\Modules\IdentityMultiFactor\Domain\AccountMfaPolicyStatus;
use Qmdb\Modules\IdentityMultiFactor\Domain\AccountMfaPreferredMethod;
use Qmdb\Modules\IdentityMultiFactor\Domain\SensitiveRecoveryCode;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\AccountMfaPolicyRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\RecoveryCodeSetRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\IdentitySessions\Domain\Repository\UserSessionRepository;
use Qmdb\Modules\IdentitySessions\Domain\SessionRevocationReason;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Time\Clock;
use SensitiveParameter;

final readonly class AccountMfaEnablementService
{
    public function __construct(
        private AccountMfaPolicyRepository $policies,
        private RecoveryCodeSetRepository $recoveryCodes,
        private SecureRecoveryCodeGenerator $generator,
        private StepUpGuard $stepUp,
        private UserSessionRepository $sessions,
        private MultiFactorNotificationService $notifications,
        private TransactionManager $transactions,
        #[SensitiveParameter] private string $identityHmacKey,
        private SecurityAuditEventAppender $audit,
        private Clock $clock,
    ) {
    }

    public function enable(
        AuthenticatedAccountContext $context,
        AccountMfaPreferredMethod $preferredMethod,
    ): RecoveryCodesOneTimeResult {
        $codes = $this->generator->generateSet();
        $hashes = array_map(
            fn (SensitiveRecoveryCode $code) => $code->normalized()->hash($this->identityHmacKey),
            $codes,
        );
        $now = $this->clock->now();
        $this->transactions->transactional(function () use ($context, $preferredMethod, $hashes, $now): void {
            $this->stepUp->consume($context, StepUpAction::MFA_ENABLE);
            $policy = $this->policies->findPolicy($context->accountInternalId, true);
            if ($policy->status === AccountMfaPolicyStatus::ENABLED) {
                throw new \DomainException('Multi-factor authentication is already enabled.');
            }
            if ($this->policies->activeStrongFactorCount($context->accountInternalId) < 1) {
                throw new \DomainException('An active authenticator is required.');
            }
            $set = $this->recoveryCodes->replaceRecoveryCodeSet($context->accountInternalId, $hashes, $now);
            if (!$this->policies->enablePolicy($policy, $preferredMethod, $now)) {
                throw new \UnexpectedValueException('MFA policy enablement failed.');
            }
            $this->sessions->revokeOthersForAccount(
                $context->accountInternalId,
                $context->sessionInternalId,
                SessionRevocationReason::MFA_POLICY_CHANGED,
                $now,
            );
            $this->audit->account(
                SecurityEventCode::MFA_ENABLED,
                $context->accountId->toString(),
                $context->accountId->toString(),
                $context->sessionId->toString(),
                $now,
                ['assurance_level' => $context->assurance->level->value],
            );
            $this->notifications->create(
                $context->accountInternalId,
                AccountSecurityNotificationType::MFA_ENABLED,
                $set->publicId,
                $now,
            );
        });

        return new RecoveryCodesOneTimeResult(array_map(
            static fn (SensitiveRecoveryCode $code): string => $code->revealOnce(),
            $codes,
        ));
    }
}
