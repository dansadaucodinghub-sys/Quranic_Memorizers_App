<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Application;

use DateTimeImmutable;
use Qmdb\Shared\Identifier\UuidV7;

interface CommunityGlobalReceipts
{
    /** @return array{public_id:string,status:string,version:int}|null */
    public function completedForActor(
        UuidV7 $submission,
        int $actorId,
        string $operation,
        string $fingerprint
    ): ?array;

    /** @return array{public_id:string,status:string,version:int}|null */
    public function claim(
        UuidV7 $submission,
        int $actorId,
        string $operation,
        string $fingerprint,
        DateTimeImmutable $now
    ): ?array;

    public function complete(
        UuidV7 $submission,
        UuidV7 $resultId,
        string $status,
        int $version,
        DateTimeImmutable $now
    ): void;
}
