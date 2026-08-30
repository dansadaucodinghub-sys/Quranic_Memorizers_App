<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Application;

use Qmdb\Modules\IdentityMultiFactor\Domain\AccountMfaPolicyStatus;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\AccountMfaPolicyRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\RecoveryCodeSetRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\IdentityMultiFactor\Domain\SensitiveRecoveryCode;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Time\Clock;
use SensitiveParameter;

final readonly class RecoveryCodeRegenerationService
{
    public function __construct(
        private AccountMfaPolicyRepository $policies,
        private RecoveryCodeSetRepository $recoveryCodes,
        private SecureRecoveryCodeGenerator $generator,
        private StepUpGuard $stepUp,
        private MultiFactorNotificationService $notifications,
        private TransactionManager $transactions,
        #[SensitiveParameter] private string $identityHmacKey,
        private SecurityAuditEventAppender $audit,
        private Clock $clock,
    ) {
    }

    public function regenerate(AuthenticatedAccountContext $context): RecoveryCodesOneTimeResult
    {
        $codes = $this->generator->generateSet();
        $hashes = array_map(
            fn (SensitiveRecoveryCode $code) => $code->normalized()->hash($this->identityHmacKey),
            $codes,
        );
        $now = $this->clock->now();
        $this->transactions->transactional(function () use ($context, $hashes, $now): void {
            $this->stepUp->consume($context, StepUpAction::MFA_REGENERATE_RECOVERY_CODES);
            $policy = $this->policies->findPolicy($context->accountInternalId, true);
            if ($policy->status !== AccountMfaPolicyStatus::ENABLED) {
                throw new \DomainException('Multi-factor authentication is not enabled.');
            }
            $set = $this->recoveryCodes->replaceRecoveryCodeSet($context->accountInternalId, $hashes, $now);
            $this->notifications->create(
                $context->accountInternalId,
                AccountSecurityNotificationType::RECOVERY_CODES_REGENERATED,
                $set->publicId,
                $now,
            );
            $this->audit->account(SecurityEventCode::RECOVERY_CODES_REGENERATED, $context->accountId->toString(), $context->accountId->toString(), $context->sessionId->toString(), $now);
        });

        return new RecoveryCodesOneTimeResult(array_map(
            static fn (SensitiveRecoveryCode $code): string => $code->revealOnce(),
            $codes,
        ));
    }
}
