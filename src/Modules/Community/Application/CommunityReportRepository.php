<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Application;

use DateTimeImmutable;
use Qmdb\Shared\Identifier\UuidV7;

interface CommunityReportRepository
{
    /** @return array{workspace_id:int,workspace_public_id:string,clip_id:int,creator_account_id:int} */
    public function visibleTarget(UuidV7 $clipId, int $reporterAccountId): array;

    /** @param array{workspace_id:int,workspace_public_id:string,clip_id:int,creator_account_id:int} $target
     * @return array{public_id:string,status:string,version:int}
     */
    public function submit(
        array $target,
        int $reporterAccountId,
        string $reasonCode,
        string $packedCiphertext,
        string $keyId,
        DateTimeImmutable $now
    ): array;
}
