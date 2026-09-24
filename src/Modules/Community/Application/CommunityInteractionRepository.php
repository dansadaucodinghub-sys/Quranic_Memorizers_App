<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Application;

use DateTimeImmutable;
use Qmdb\Shared\Identifier\UuidV7;

interface CommunityInteractionRepository
{
    /** @return list<UuidV7> */
    public function bookmarkedClipIds(int $actorId): array;

    /** @return array{reaction:array{active:bool,version:int},bookmark:array{active:bool,version:int}} */
    public function state(int $workspaceId, UuidV7 $clipId, int $actorId): array;

    /** @return array{public_id:string,status:string,version:int} */
    public function transition(
        int $workspaceId,
        UuidV7 $clipId,
        int $actorId,
        string $kind,
        string $action,
        int $expectedVersion,
        DateTimeImmutable $now
    ): array;
}
