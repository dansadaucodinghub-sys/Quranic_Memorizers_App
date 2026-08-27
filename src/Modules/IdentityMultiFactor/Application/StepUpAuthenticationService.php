<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Application;

use Qmdb\Modules\IdentityAccess\Application\Authentication\PasswordAuthenticationRepository;
use Qmdb\Modules\IdentityAccess\Security\Password\PasswordVerifier;
use Qmdb\Modules\IdentityAccess\Security\Password\SensitivePlaintextPassword;
use Qmdb\Modules\IdentityMultiFactor\Configuration\IdentityMultiFactorConfiguration;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationMethod;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationTransactionCookieValue;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationTransactionPurpose;
use Qmdb\Modules\IdentityMultiFactor\Domain\NormalizedRecoveryCode;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\AccountMfaPolicyRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\AuthenticationTransactionRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\RecoveryCodeSetRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\StepUpGrantRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\TotpAuthenticatorRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\IdentityMultiFactor\Domain\TotpSecretEncryptor;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Time\Clock;
use SensitiveParameter;

final readonly class StepUpAuthenticationService
{
    public function __construct(
        private AuthenticationTransactionRepository $transactions,
        private StepUpGrantRepository $grants,
        private AccountMfaPolicyRepository $policies,
        private TotpAuthenticatorRepository $totpAuthenticators,
        private RecoveryCodeSetRepository $recoveryCodes,
        private PasswordAuthenticationRepository $passwords,
        private PasswordVerifier $passwordVerifier,
        private TotpSecretEncryptor $totpSecrets,
        private TotpVerifier $totpVerifier,
        private TransactionManager $databaseTransactions,
        private AuthenticationTransactionCookieFactory $cookies,
        private IdentityMultiFactorConfiguration $configuration,
        #[SensitiveParameter] private string $identityHmacKey,
        private Clock $clock,
    ) {
    }

    public function start(AuthenticatedAccountContext $context, StepUpAction $action): StepUpAuthenticationResult
    {
        $now = $this->clock->now();
        $methods = $this->eligibleMethods($context, $action);
        if ($methods === []) {
            return StepUpAuthenticationResult::rejected('NO_ELIGIBLE_METHOD');
        }
        $cookie = $this->databaseTransactions->transactional(function () use ($context, $action, $methods, $now) {
            $this->transactions->revokePendingStepUpForSession($context->sessionInternalId, $now);

            return $this->transactions->createTransaction(
                $context->accountInternalId,
                $context->sessionInternalId,
                AuthenticationTransactionPurpose::STEP_UP,
                $action,
                null,
                $methods,
                $this->configuration->stepUpMaximumAttempts,
                $now,
                $now->modify('+' . $this->configuration->transactionTtlSeconds . ' seconds'),
            );
        });

        return StepUpAuthenticationResult::started($action, $this->cookies->issue($cookie));
    }

    /** @return list<AuthenticationMethod> */
    public function eligibleMethods(
        AuthenticatedAccountContext $context,
        StepUpAction $action,
    ): array {
        $active = $this->policies->activeMethods($context->accountInternalId);

        return $action->requirement() === AuthenticationAssuranceLevel::PRIMARY
            ? array_values(array_unique([AuthenticationMethod::PASSWORD, ...$active], SORT_REGULAR))
            : array_values(array_filter(
                $active,
                static fn (AuthenticationMethod $method): bool => $method !== AuthenticationMethod::PASSWORD,
            ));
    }

    public function password(
        AuthenticatedAccountContext $context,
        AuthenticationTransactionCookieValue $cookie,
        SensitivePlaintextPassword $password,
    ): StepUpAuthenticationResult {
        $record = $this->passwords->byAccount($context->accountInternalId);
        if ($record === null || !$this->passwordVerifier->verify($password, $record->passwordHash)->verified) {
            $this->fail($cookie);
            return StepUpAuthenticationResult::rejected('INVALID_AUTHENTICATOR');
        }

        return $this->complete(
            $context,
            $cookie,
            AuthenticationMethod::PASSWORD,
            AuthenticationAssuranceLevel::PRIMARY,
        );
    }

    public function totp(
        AuthenticatedAccountContext $context,
        AuthenticationTransactionCookieValue $cookie,
        #[SensitiveParameter] string $code,
    ): StepUpAuthenticationResult {
        $authenticator = $this->totpAuthenticators->findActiveTotp($context->accountInternalId);
        if ($authenticator === null) {
            $this->fail($cookie);
            return StepUpAuthenticationResult::rejected('INVALID_AUTHENTICATOR');
        }
        $secret = $this->totpSecrets->decrypt(
            $authenticator->secret,
            $context->accountId->toString(),
            $authenticator->publicId,
        );
        $counter = $this->totpVerifier->acceptedCounter(
            $secret,
            $code,
            $this->clock->now(),
            $authenticator->lastAcceptedCounter,
        );
        if ($counter === null) {
            $this->fail($cookie);
            return StepUpAuthenticationResult::rejected('INVALID_AUTHENTICATOR');
        }

        return $this->complete(
            $context,
            $cookie,
            AuthenticationMethod::TOTP,
            AuthenticationAssuranceLevel::MULTI_FACTOR,
            function () use ($context, $authenticator, $counter): bool {
                $locked = $this->totpAuthenticators->findActiveTotp($context->accountInternalId, true);

                return $locked !== null
                    && $locked->internalId === $authenticator->internalId
                    && $this->totpAuthenticators->acceptTotpCounter($locked, $counter, $this->clock->now());
            },
        );
    }

    public function recoveryCode(
        AuthenticatedAccountContext $context,
        AuthenticationTransactionCookieValue $cookie,
        #[SensitiveParameter] string $code,
    ): StepUpAuthenticationResult {
        try {
            $hash = NormalizedRecoveryCode::fromInput($code)->hash($this->identityHmacKey);
        } catch (\InvalidArgumentException) {
            $this->fail($cookie);
            return StepUpAuthenticationResult::rejected('INVALID_AUTHENTICATOR');
        }

        return $this->complete(
            $context,
            $cookie,
            AuthenticationMethod::RECOVERY_CODE,
            AuthenticationAssuranceLevel::MULTI_FACTOR,
            function () use ($context, $hash): bool {
                $set = $this->recoveryCodes->findActiveRecoveryCodeSet($context->accountInternalId, true);

                return $set !== null && $this->recoveryCodes->consumeRecoveryCode($set, $hash, $this->clock->now());
            },
        );
    }

    public function passkey(
        AuthenticatedAccountContext $context,
        AuthenticationTransactionCookieValue $cookie,
    ): StepUpAuthenticationResult {
        return $this->complete(
            $context,
            $cookie,
            AuthenticationMethod::PASSKEY,
            AuthenticationAssuranceLevel::PHISHING_RESISTANT,
        );
    }

    private function complete(
        AuthenticatedAccountContext $context,
        AuthenticationTransactionCookieValue $cookie,
        AuthenticationMethod $method,
        AuthenticationAssuranceLevel $assurance,
        ?\Closure $factorMutation = null,
    ): StepUpAuthenticationResult {
        $now = $this->clock->now();
        $action = $this->databaseTransactions->transactional(function () use (
            $context,
            $cookie,
            $method,
            $assurance,
            $factorMutation,
            $now,
        ): ?StepUpAction {
            $transaction = $this->transactions->findTransaction($cookie, true);
            if (
                $transaction === null || !$transaction->usableAt($now)
                || $transaction->purpose !== AuthenticationTransactionPurpose::STEP_UP
                || $transaction->accountInternalId !== $context->accountInternalId
                || $transaction->sessionInternalId !== $context->sessionInternalId
                || $transaction->targetAction === null
                || !in_array($method, $transaction->allowedMethods, true)
                || !$assurance->satisfies($transaction->targetAction->requirement())
            ) {
                return null;
            }
            if ($factorMutation !== null && $factorMutation() !== true) {
                throw new \UnexpectedValueException('Step-up factor mutation failed.');
            }
            if (!$this->transactions->completeTransaction($transaction, $now)) {
                throw new \UnexpectedValueException('Step-up transaction completion failed.');
            }
            $this->grants->createGrant(
                $context->accountInternalId,
                $context->sessionInternalId,
                $transaction->targetAction,
                $assurance,
                $now,
                $now->modify('+' . $this->configuration->stepUpGrantTtlSeconds . ' seconds'),
            );

            return $transaction->targetAction;
        });
        if ($action === null) {
            $this->fail($cookie);
            return StepUpAuthenticationResult::rejected('INVALID_TRANSACTION');
        }

        return StepUpAuthenticationResult::completed($action, $this->cookies->clear());
    }

    private function fail(AuthenticationTransactionCookieValue $cookie): void
    {
        $now = $this->clock->now();
        $this->databaseTransactions->transactional(function () use ($cookie, $now): void {
            $transaction = $this->transactions->findTransaction($cookie, true);
            if ($transaction !== null && $transaction->usableAt($now)) {
                $this->transactions->recordTransactionFailure($transaction, $now);
            }
        });
    }
}
