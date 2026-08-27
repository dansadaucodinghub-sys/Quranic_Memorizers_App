<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityMultiFactor\Configuration;

final readonly class IdentityMultiFactorConfiguration
{
    /** @param list<string> $allowedOrigins */
    public function __construct(
        public bool $productionLike,
        public int $transactionTtlSeconds,
        public int $transactionMaximumAttempts,
        public int $stepUpGrantTtlSeconds,
        public int $stepUpMaximumAttempts,
        public int $encryptionKeyVersion,
        public string $totpIssuer,
        public int $totpPeriodSeconds,
        public int $totpDigits,
        public int $totpAllowedDriftSteps,
        public int $totpEnrollmentTtlSeconds,
        public int $recoveryCodeCount,
        public int $recoveryCodeBytes,
        public string $relyingPartyId,
        public string $relyingPartyName,
        public array $allowedOrigins,
        public int $webauthnChallengeTtlSeconds,
        public int $webauthnMaximumResponseBytes,
        public string $userVerification,
        public string $attestation,
        public bool $passwordlessEnabled,
        public int $mfaWindowSeconds,
        public int $mfaMaximumAttempts,
        public int $passkeyWindowSeconds,
        public int $passkeyMaximumAttempts,
    ) {
    }
}
