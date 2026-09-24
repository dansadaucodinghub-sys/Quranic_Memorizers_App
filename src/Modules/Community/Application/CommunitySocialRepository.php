<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Application;

use DateTimeImmutable;
use Qmdb\Shared\Identifier\UuidV7;

interface CommunitySocialRepository
{
    /** @return array{follow_status:string,follow_version:int,block_active:bool,block_version:int,mute_active:bool,mute_version:int,own_profile:bool} */
    public function state(int $actorId, UuidV7 $targetProfileId): array;

    /** @return list<array{profile_id:string,alias:string,block_version:int,mute_version:int,blocked:bool,muted:bool}> */
    public function safetyList(int $actorId): array;

    /** @return list<array{profile_id:string,alias:string,status:string,version:int}> */
    public function incoming(int $actorId): array;

    /** @return list<array{profile_id:string,alias:string,status:string,version:int}> */
    public function outgoing(int $actorId): array;

    /**
     * Performs a version-checked transition while holding both account locks. The
     * returned identifier belongs to the relationship or its immutable event.
     *
     * @return array{public_id:string,status:string,version:int}
     */
    public function transition(
        int $actorId,
        UuidV7 $targetProfileId,
        string $action,
        int $expectedVersion,
        DateTimeImmutable $now
    ): array;
}
