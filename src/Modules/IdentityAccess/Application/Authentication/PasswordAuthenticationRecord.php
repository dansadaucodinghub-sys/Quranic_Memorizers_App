<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Application\Authentication;

use Qmdb\Modules\Identity\Domain\AccountContactStatus;
use Qmdb\Modules\Identity\Domain\AccountStatus;
use Qmdb\Modules\Identity\Domain\CredentialStatus;
use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\Identity\Domain\Value\SensitivePasswordHash;

final readonly class PasswordAuthenticationRecord
{
    public function __construct(
        public int $accountInternalId,
        public AccountId $accountId,
        public AccountStatus $accountStatus,
        public AccountContactStatus $emailStatus,
        public CredentialStatus $credentialStatus,
        public SensitivePasswordHash $passwordHash,
        public string $algorithm,
        public int $metadataVersion,
    ) {
    }
}
