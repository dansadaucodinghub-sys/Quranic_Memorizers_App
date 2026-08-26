<?php

declare(strict_types=1);

namespace Qmdb\Modules\Identity\Domain;

use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\Identity\Domain\Value\SensitivePasswordHash;

final readonly class AccountAuthenticationRecord
{
    public function __construct(
        public int $accountInternalId,
        public AccountId $accountId,
        public AccountStatus $accountStatus,
        public SensitivePasswordHash $passwordHash,
        public string $algorithm,
        public int $metadataVersion,
        public int $credentialVersion,
    ) {
    }
}
