<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityRecovery\Application;

use DateTimeImmutable;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryChallengeId;
use Qmdb\Modules\IdentityRecovery\Domain\PasswordRecoveryToken;

final readonly class PasswordRecoveryDelivery
{
    public function __construct(
        public int $challengeInternalId,
        public int $accountInternalId,
        public string $emailCiphertext,
        public string $locale,
        public PasswordRecoveryChallengeId $challengeId,
        public PasswordRecoveryToken $token,
        public DateTimeImmutable $expiresAt,
    ) {
    }
}
