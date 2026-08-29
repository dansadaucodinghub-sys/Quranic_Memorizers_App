<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace Qmdb\Modules\SecurityPrivilegedAccess\Application;

use DateTimeImmutable;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessRequestId;

interface PrivilegedAccessRequestRepository
{
    public function create(PrivilegedAccessRequestCommand $command, DateTimeImmutable $now, DateTimeImmutable $expiresAt): PrivilegedAccessRequestId;
}
