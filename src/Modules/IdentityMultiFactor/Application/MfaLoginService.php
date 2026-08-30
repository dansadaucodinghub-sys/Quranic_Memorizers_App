<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Application;

use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationMethod;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationTransactionCookieValue;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationTransactionPurpose;
use Qmdb\Modules\IdentityMultiFactor\Domain\NormalizedRecoveryCode;
use Qmdb\Modules\IdentityMultiFactor\Domain\PasskeyCredential;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\AuthenticationTransactionRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\RecoveryCodeSetRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\MultiFactorNotificationTargetRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\TotpAuthenticatorRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\SessionAuthenticationAssurance;
use Qmdb\Modules\IdentityMultiFactor\Domain\TotpSecretEncryptor;
use Qmdb\Modules\IdentitySecurityNotifications\Domain\AccountSecurityNotificationType;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Time\Clock;
use SensitiveParameter;

final readonly class MfaLoginService
{
    public function __construct(
        private AuthenticationTransactionRepository $authenticationTransactions,
        private TotpAuthenticatorRepository $totpAuthenticators,
        private RecoveryCodeSetRepository $recoveryCodes,
        private MultiFactorNotificationTargetRepository $targets,
        private TotpSecretEncryptor $totpSecrets,
        private TotpVerifier $totpVerifier,
        private StrongSessionIssuanceService $sessions,
        private MultiFactorNotificationService $notifications,
        private TransactionManager $transactions,
        private AuthenticationTransactionCookieFactory $cookies,
        #[SensitiveParameter] private string $identityHmacKey,
        private SecurityAuditEventAppender $audit,
        private Clock $clock,
    ) {
    }

    /** @return list<AuthenticationMethod> */
    public function allowedMethods(AuthenticationTransactionCookieValue $cookie): array
    {
        $transaction = $this->authenticationTransactions->findTransaction($cookie);
        if (
            $transaction === null || !$transaction->usableAt($this->clock->now())
            || $transaction->purpose !== AuthenticationTransactionPurpose::LOGIN_MFA
        ) {
            return [];
        }

        return $transaction->allowedMethods;
    }

    public function totp(
        AuthenticationTransactionCookieValue $cookie,
        #[SensitiveParameter] string $code,
        ?string $deviceCookie,
    ): MfaLoginResult {
        $candidate = $this->authenticationTransactions->findTransaction($cookie);
        if (
            $candidate === null || $candidate->accountInternalId === null
            || !in_array(AuthenticationMethod::TOTP, $candidate->allowedMethods, true)
        ) {
            return MfaLoginResult::rejected('INVALID_TRANSACTION');
        }
        $authenticator = $this->totpAuthenticators->findActiveTotp($candidate->accountInternalId);
        if ($authenticator === null) {
            $this->fail($cookie);
            return MfaLoginResult::rejected('INVALID_AUTHENTICATOR');
        }
        $target = $this->targets->notificationTarget($candidate->accountInternalId);
        if ($target === null) {
            return MfaLoginResult::rejected('INVALID_AUTHENTICATOR');
        }
        try {
            $secret = $this->totpSecrets->decrypt(
                $authenticator->secret,
                $target['account_public_id'],
                $authenticator->publicId,
            );
        } catch (\Throwable) {
            return MfaLoginResult::rejected('INVALID_AUTHENTICATOR');
        }
        $now = $this->clock->now();
        $counter = $this->totpVerifier->acceptedCounter(
            $secret,
            $code,
            $now,
            $authenticator->lastAcceptedCounter,
        );
        if ($counter === null) {
            $this->fail($cookie);
            return MfaLoginResult::rejected('INVALID_AUTHENTICATOR');
        }

        return $this->transactions->transactional(function () use (
            $cookie,
            $deviceCookie,
            $counter,
            $now,
        ): MfaLoginResult {
            $transaction = $this->authenticationTransactions->findTransaction($cookie, true);
            if (
                $transaction === null || !$transaction->usableAt($now) || $transaction->accountInternalId === null
                || $transaction->purpose !== AuthenticationTransactionPurpose::LOGIN_MFA
                || !in_array(AuthenticationMethod::TOTP, $transaction->allowedMethods, true)
            ) {
                return MfaLoginResult::rejected('INVALID_TRANSACTION');
            }
            $authenticator = $this->totpAuthenticators->findActiveTotp($transaction->accountInternalId, true);
            if (
                $authenticator === null
                || !$this->totpAuthenticators->acceptTotpCounter($authenticator, $counter, $now)
                || !$this->authenticationTransactions->completeTransaction($transaction, $now)
            ) {
                throw new \DomainException('TOTP assertion changed concurrently.');
            }
            $instructions = $this->sessions->issueWithinTransaction(
                $transaction->accountInternalId,
                $deviceCookie,
                new SessionAuthenticationAssurance(
                    AuthenticationMethod::PASSWORD,
                    AuthenticationMethod::TOTP,
                    AuthenticationAssuranceLevel::MULTI_FACTOR,
                    $now,
                    $now,
                ),
            );
            $instructions[] = $this->cookies->clear();

            return MfaLoginResult::authenticated($instructions);
        });
    }

    public function recoveryCode(
        AuthenticationTransactionCookieValue $cookie,
        #[SensitiveParameter] string $code,
        ?string $deviceCookie,
    ): MfaLoginResult {
        try {
            $hash = NormalizedRecoveryCode::fromInput($code)->hash($this->identityHmacKey);
        } catch (\InvalidArgumentException) {
            $this->fail($cookie);
            return MfaLoginResult::rejected('INVALID_AUTHENTICATOR');
        }
        $now = $this->clock->now();

        return $this->transactions->transactional(function () use (
            $cookie,
            $deviceCookie,
            $hash,
            $now,
        ): MfaLoginResult {
            $transaction = $this->authenticationTransactions->findTransaction($cookie, true);
            if (
                $transaction === null || !$transaction->usableAt($now) || $transaction->accountInternalId === null
                || $transaction->purpose !== AuthenticationTransactionPurpose::LOGIN_MFA
                || !in_array(AuthenticationMethod::RECOVERY_CODE, $transaction->allowedMethods, true)
            ) {
                return MfaLoginResult::rejected('INVALID_TRANSACTION');
            }
            $set = $this->recoveryCodes->findActiveRecoveryCodeSet($transaction->accountInternalId, true);
            if (
                $set === null || !$this->recoveryCodes->consumeRecoveryCode($set, $hash, $now)
                || !$this->authenticationTransactions->completeTransaction($transaction, $now)
            ) {
                throw new \DomainException('Recovery-code assertion is invalid or changed concurrently.');
            }
            $instructions = $this->sessions->issueWithinTransaction(
                $transaction->accountInternalId,
                $deviceCookie,
                new SessionAuthenticationAssurance(
                    AuthenticationMethod::PASSWORD,
                    AuthenticationMethod::RECOVERY_CODE,
                    AuthenticationAssuranceLevel::MULTI_FACTOR,
                    $now,
                    $now,
                ),
            );
            $this->notifications->create(
                $transaction->accountInternalId,
                AccountSecurityNotificationType::RECOVERY_CODE_USED,
                $set->publicId,
                $now,
            );
            $target = $this->targets->notificationTarget($transaction->accountInternalId);
            if ($target === null) {
                throw new \UnexpectedValueException('Recovery-code audit account identity is unavailable.');
            }
            $this->audit->account(
                SecurityEventCode::RECOVERY_CODE_USED,
                $target['account_public_id'],
                $target['account_public_id'],
                null,
                $now,
            );
            $instructions[] = $this->cookies->clear();

            return MfaLoginResult::authenticated($instructions);
        });
    }

    public function passkey(
        AuthenticationTransactionCookieValue $cookie,
        PasskeyCredential $passkey,
        ?string $deviceCookie,
    ): MfaLoginResult {
        $now = $this->clock->now();

        return $this->transactions->transactional(function () use (
            $cookie,
            $passkey,
            $deviceCookie,
            $now,
        ): MfaLoginResult {
            $transaction = $this->authenticationTransactions->findTransaction($cookie, true);
            if (
                $transaction === null || !$transaction->usableAt($now)
                || $transaction->purpose !== AuthenticationTransactionPurpose::LOGIN_MFA
                || $transaction->accountInternalId !== $passkey->accountInternalId
                || !in_array(AuthenticationMethod::PASSKEY, $transaction->allowedMethods, true)
                || !$this->authenticationTransactions->completeTransaction($transaction, $now)
            ) {
                return MfaLoginResult::rejected('INVALID_TRANSACTION');
            }
            $instructions = $this->sessions->issueWithinTransaction(
                $passkey->accountInternalId,
                $deviceCookie,
                new SessionAuthenticationAssurance(
                    AuthenticationMethod::PASSWORD,
                    AuthenticationMethod::PASSKEY,
                    AuthenticationAssuranceLevel::PHISHING_RESISTANT,
                    $transaction->createdAt,
                    $now,
                ),
            );
            $instructions[] = $this->cookies->clear();

            return MfaLoginResult::authenticated($instructions);
        });
    }

    public function passwordless(PasskeyCredential $passkey, ?string $deviceCookie): MfaLoginResult
    {
        $now = $this->clock->now();

        return $this->transactions->transactional(function () use ($passkey, $deviceCookie, $now): MfaLoginResult {
            return MfaLoginResult::authenticated($this->sessions->issueWithinTransaction(
                $passkey->accountInternalId,
                $deviceCookie,
                new SessionAuthenticationAssurance(
                    AuthenticationMethod::PASSKEY,
                    null,
                    AuthenticationAssuranceLevel::PHISHING_RESISTANT,
                    $now,
                    $now,
                ),
            ));
        });
    }

    private function fail(AuthenticationTransactionCookieValue $cookie): void
    {
        $now = $this->clock->now();
        $this->transactions->transactional(function () use ($cookie, $now): void {
            $transaction = $this->authenticationTransactions->findTransaction($cookie, true);
            if ($transaction !== null && $transaction->usableAt($now)) {
                $this->authenticationTransactions->recordTransactionFailure($transaction, $now);
            }
        });
    }
}
