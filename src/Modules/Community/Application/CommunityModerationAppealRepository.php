<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Application;

use DateTimeImmutable;
use Qmdb\Shared\Identifier\UuidV7;

interface CommunityModerationAppealRepository
{
    /** @return list<array{case_id:string,clip_id:string,action:string,decided_at:string}> */
    public function ownerEligible(int $workspaceId, int $appellantAccountId, DateTimeImmutable $since): array;

    /** @return array{workspace_id:int,case_id:int,decision_id:int,clip_id:UuidV7,decision_reviewer_id:int,decided_at:DateTimeImmutable}|null */
    public function eligibleDecision(int $workspaceId, UuidV7 $caseId, int $appellantAccountId): ?array;

    /** @param array{workspace_id:int,case_id:int,decision_id:int,clip_id:UuidV7,decision_reviewer_id:int,decided_at:DateTimeImmutable} $decision */
    public function submit(array $decision, int $appellantAccountId, string $ciphertext, string $keyId, DateTimeImmutable $now): UuidV7;

    /** @return list<array{public_id:string,case_id:string,clip_id:string,submitted_at:string}> */
    public function queue(int $workspaceId, int $reviewerAccountId): array;

    /** @return array{packed_ciphertext:string,key_id:string}|null */
    public function statement(int $workspaceId, UuidV7 $appealId, int $reviewerAccountId): ?array;

    /** @return array{id:int,public_id:UuidV7,workspace_id:int,case_id:int,case_status:string,case_version:int,clip_id:UuidV7,status:string,version:int,appellant_account_id:int,decision_reviewer_id:int}|null */
    public function lock(int $workspaceId, UuidV7 $appealId, int $reviewerAccountId): ?array;

    /** @param array{id:int,public_id:UuidV7,workspace_id:int,case_id:int,case_status:string,case_version:int,clip_id:UuidV7,status:string,version:int,appellant_account_id:int,decision_reviewer_id:int} $appeal */
    public function decide(array $appeal, int $reviewerAccountId, string $outcome, string $reasonCode, DateTimeImmutable $now): void;
}
