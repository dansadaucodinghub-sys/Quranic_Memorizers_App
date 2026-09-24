<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Application;

use DateTimeImmutable;
use Qmdb\Shared\Identifier\UuidV7;

interface CommunityModerationRepository
{
    /** @return list<array{public_id:string,clip_id:string,asset_id:string,clip_status:string,status:string,priority:string,version:int,assigned_to_me:bool,report_count:int}> */
    public function queue(int $workspaceId, int $reviewerAccountId): array;

    /** @return list<array{reason:string,packed_ciphertext:string,key_id:string,created_at:string}> */
    public function caseReports(int $workspaceId, UuidV7 $caseId, int $reviewerAccountId): array;

    /** @return array{id:int,public_id:UuidV7,workspace_id:int,clip_public_id:UuidV7,creator_account_id:int,status:string,version:int,assigned_reviewer_id:?int}|null */
    public function lock(int $workspaceId, UuidV7 $caseId): ?array;

    /** @param array{id:int,public_id:UuidV7,workspace_id:int,clip_public_id:UuidV7,creator_account_id:int,status:string,version:int,assigned_reviewer_id:?int} $case */
    public function assignSelf(array $case, int $reviewerAccountId, DateTimeImmutable $now): void;

    /** @param array{id:int,public_id:UuidV7,workspace_id:int,clip_public_id:UuidV7,creator_account_id:int,status:string,version:int,assigned_reviewer_id:?int} $case */
    public function startReview(array $case, int $reviewerAccountId, DateTimeImmutable $now): void;

    /** @param array{id:int,public_id:UuidV7,workspace_id:int,clip_public_id:UuidV7,creator_account_id:int,status:string,version:int,assigned_reviewer_id:?int} $case */
    public function decide(
        array $case,
        int $reviewerAccountId,
        string $action,
        string $reason,
        DateTimeImmutable $now
    ): string;
}
