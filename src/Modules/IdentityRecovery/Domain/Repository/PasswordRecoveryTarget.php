<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityRecovery\Domain\Repository;

use Qmdb\Modules\Identity\Domain\AccountContactStatus;
use Qmdb\Modules\Identity\Domain\AccountStatus;
use Qmdb\Modules\Identity\Domain\Value\AccountId;

final readonly class PasswordRecoveryTarget
{
    public function __construct(
        public int $accountInternalId,
        public AccountId $accountId,
        public int $emailInternalId,
        public string $emailCiphertext,
        public AccountStatus $accountStatus,
        public AccountContactStatus $emailStatus,
        public string $locale,
        public bool $hasActivePassword,
    ) {
    }

    public function eligible(): bool
    {
        return $this->accountStatus === AccountStatus::ACTIVE
            && $this->emailStatus === AccountContactStatus::VERIFIED
            && $this->hasActivePassword;
    }
}
