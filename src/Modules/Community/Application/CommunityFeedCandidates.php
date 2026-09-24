<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Application;

use Qmdb\Shared\Identifier\UuidV7;

interface CommunityFeedCandidates
{
    public function publicProfileAlias(UuidV7 $profileId, ?int $viewerAccountId): ?string;

    /**
     * @return list<array{clip_id:UuidV7,published_at:string,internal_id:int}>
     */
    public function newest(
        ?int $viewerAccountId,
        ?string $beforeTime,
        ?int $beforeId,
        ?string $language,
        ?int $surah,
        bool $followingOnly,
        ?UuidV7 $profileId
    ): array;
}
