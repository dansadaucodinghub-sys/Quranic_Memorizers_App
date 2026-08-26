<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Configuration;

final readonly class IdentityAccessConfiguration
{
    public function __construct(
        public PublicApplicationBaseUrl $publicBaseUrl,
        public bool $productionLike,
        public int $csrfTtlSeconds,
        public int $formMaxBytes,
        public int $passwordMinimumLength,
        public int $passwordMaximumBytes,
        public int $verificationTtlSeconds,
        public int $verificationMaximumAttempts,
        public int $registrationWindowSeconds,
        public int $registrationMaximumAttempts,
        public int $resendWindowSeconds,
        public int $resendMaximumAttempts,
        public int $passwordWindowSeconds,
        public int $passwordMaximumAttempts,
        public int $rateLimitBlockSeconds,
        public string $contactEncryptionKeyId,
        public string $mailFromAddress,
        public string $mailFromName,
    ) {
    }
}
