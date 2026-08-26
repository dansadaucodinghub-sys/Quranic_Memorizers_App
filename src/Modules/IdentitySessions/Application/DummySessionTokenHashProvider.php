<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Application;

use Qmdb\Modules\IdentitySessions\Domain\SessionTokenHash;

final readonly class DummySessionTokenHashProvider
{
    public function hash(): SessionTokenHash
    {
        return new SessionTokenHash(hash('sha256', 'qmdb-session-selector-timing-dummy-v1', true));
    }
}
