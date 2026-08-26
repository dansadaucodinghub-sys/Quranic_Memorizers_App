<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\IdentityAccess;

use Qmdb\Modules\Identity\Domain\Value\LookupHash;
use Qmdb\Modules\IdentityAccess\Application\Authentication\PasswordAuthenticationRecord;
use Qmdb\Modules\IdentityAccess\Application\Authentication\PasswordAuthenticationRepository;

final class RecordingAuthenticationRepository implements PasswordAuthenticationRepository
{
    public int $calls = 0;

    public function __construct(private readonly ?PasswordAuthenticationRecord $record)
    {
    }

    public function byEmailHash(LookupHash $lookupHash): ?PasswordAuthenticationRecord
    {
        $this->calls++;
        return $this->record;
    }
}
