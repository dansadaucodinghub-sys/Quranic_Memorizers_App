<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Application;

use DateTimeImmutable;
use Qmdb\Shared\Identifier\UuidV7;

interface CommunityNotificationIntentRepository
{
    public function enqueue(
        int $workspaceId,
        int $recipientAccountId,
        string $typeCode,
        UuidV7 $subjectPublicId,
        int $subjectVersion,
        string $safeStateCode,
        DateTimeImmutable $now,
    ): void;

    /** @return list<array{id:int,public_id:UuidV7,recipient_account_id:int,type_code:string,subject_public_id:UuidV7,safe_state_code:string,locale:string,attempt_count:int,email_ciphertext:?string,email_key_id:?string}> */
    public function claimDue(UuidV7 $leaseOwner, DateTimeImmutable $now, DateTimeImmutable $leaseExpiresAt, int $limit): array;

    public function delivered(int $id, UuidV7 $leaseOwner, DateTimeImmutable $now): bool;

    public function retry(int $id, UuidV7 $leaseOwner, string $errorCode, DateTimeImmutable $nextAttempt, DateTimeImmutable $now): bool;

    public function deadLetter(int $id, UuidV7 $leaseOwner, string $errorCode, DateTimeImmutable $now): bool;
}
