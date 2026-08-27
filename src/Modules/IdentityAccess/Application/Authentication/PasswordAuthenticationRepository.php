<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Application\Authentication;

use DateTimeImmutable;
use Qmdb\Modules\Identity\Domain\Value\LookupHash;
use Qmdb\Modules\IdentityAccess\Security\Password\PasswordHashResult;

interface PasswordAuthenticationRepository
{
    public function byEmailHash(LookupHash $lookupHash): ?PasswordAuthenticationRecord;

    public function byAccount(int $accountInternalId): ?PasswordAuthenticationRecord;

    public function replacePasswordHash(
        int $accountInternalId,
        PasswordHashResult $hash,
        DateTimeImmutable $updatedAt,
    ): bool;
}
