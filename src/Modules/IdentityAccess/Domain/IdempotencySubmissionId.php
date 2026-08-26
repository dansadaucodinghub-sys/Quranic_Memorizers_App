<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Domain;

interface IdempotencySubmissionId
{
    public function toString(): string;

    public function toBinary(): string;
}
