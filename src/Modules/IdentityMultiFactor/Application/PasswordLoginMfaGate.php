<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Application;

use DateTimeImmutable;
use Qmdb\Modules\IdentityMultiFactor\Configuration\IdentityMultiFactorConfiguration;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationMethod;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationTransactionCookieValue;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationTransactionPurpose;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\AccountMfaPolicyRepository;
use Qmdb\Modules\IdentityMultiFactor\Domain\Repository\AuthenticationTransactionRepository;

final readonly class PasswordLoginMfaGate
{
    public function __construct(
        private AccountMfaPolicyRepository $policies,
        private AuthenticationTransactionRepository $transactions,
        private IdentityMultiFactorConfiguration $configuration,
    ) {
    }

    public function beginWhenRequired(
        int $accountInternalId,
        DateTimeImmutable $now,
    ): ?AuthenticationTransactionCookieValue {
        $policy = $this->policies->findPolicy($accountInternalId);
        if (!$policy->enabled()) {
            return null;
        }
        $methods = array_values(array_filter(
            $this->policies->activeMethods($accountInternalId),
            static fn (AuthenticationMethod $method): bool => in_array(
                $method,
                [AuthenticationMethod::TOTP, AuthenticationMethod::RECOVERY_CODE, AuthenticationMethod::PASSKEY],
                true,
            ),
        ));
        if ($methods === []) {
            throw new \DomainException('Enabled MFA policy has no usable authenticator.');
        }

        return $this->transactions->createTransaction(
            $accountInternalId,
            null,
            AuthenticationTransactionPurpose::LOGIN_MFA,
            null,
            AuthenticationMethod::PASSWORD,
            $methods,
            $this->configuration->transactionMaximumAttempts,
            $now,
            $now->modify('+' . $this->configuration->transactionTtlSeconds . ' seconds'),
        );
    }
}
