<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Application\Authentication;

use Qmdb\Modules\Identity\Domain\Value\LookupHash;

interface PasswordAuthenticationRepository
{
    public function byEmailHash(LookupHash $lookupHash): ?PasswordAuthenticationRecord;
}
