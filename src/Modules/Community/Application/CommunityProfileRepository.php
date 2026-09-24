<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Application;

use DateTimeImmutable;
use Qmdb\Shared\Identifier\UuidV7;

interface CommunityProfileRepository
{
    /** @return array{public_id:string,alias:string,visibility:string,version:int}|null */
    public function mine(int $accountId): ?array;

    /** @return array{public_id:string,visibility:string,version:int} */
    public function createPrivate(int $accountId, string $alias, DateTimeImmutable $now): array;

    /** @return array{public_id:string,visibility:string,version:int} */
    public function changeVisibility(
        int $accountId,
        UuidV7 $profileId,
        int $expectedVersion,
        string $visibility,
        DateTimeImmutable $now
    ): array;

    /** @return array{public_id:string,visibility:string,version:int} */
    public function updateOwn(
        int $accountId,
        UuidV7 $profileId,
        int $expectedVersion,
        string $alias,
        string $visibility,
        DateTimeImmutable $now
    ): array;
}
