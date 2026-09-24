<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Application;

use DateTimeImmutable;
use Qmdb\Shared\Identifier\UuidV7;

interface CommunityCommentRepository
{
    /** @return list<array{public_id:string,clip_id:string,body:string,version:int,created_at:string}> */
    public function heldQueue(int $workspaceId, int $reviewerAccountId): array;

    /** @return array{public_id:string,status:string,version:int} */
    public function moderateHeld(
        int $workspaceId,
        UuidV7 $commentId,
        int $reviewerAccountId,
        string $action,
        int $expectedVersion,
        DateTimeImmutable $now,
    ): array;

    /** @return list<array{public_id:string,parent_id:?string,body:string,status:string,version:int,created_at:string,is_mine:bool}> */
    public function visible(int $workspaceId, UuidV7 $clipId, ?int $viewerAccountId): array;

    /** @return array{public_id:string,status:string,version:int} */
    public function create(
        int $workspaceId,
        UuidV7 $clipId,
        int $actorId,
        ?UuidV7 $parentId,
        string $body,
        DateTimeImmutable $now
    ): array;

    /** @return array{public_id:string,status:string,version:int} */
    public function transition(
        int $workspaceId,
        UuidV7 $clipId,
        UuidV7 $commentId,
        int $actorId,
        string $action,
        int $expectedVersion,
        string $body,
        DateTimeImmutable $now
    ): array;
}
