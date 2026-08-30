<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccountState\Application;

use DateTimeImmutable;
use Qmdb\Modules\IdentityAccountState\Domain\AccountStateOperationType;
use Qmdb\Modules\IdentityAccountState\Domain\AccountStateReasonCode;
use Qmdb\Shared\Identifier\UuidV7;

interface AccountStateRepository
{
    /** @return array{id:int, public_id:string, account_status:string, version:int}|null */
    public function lockAccount(UuidV7 $publicId): ?array;

    public function anotherUsableSecurityAdministratorExists(int $targetAccountId): bool;

    public function transition(int $accountId, string $from, string $to, int $expectedVersion, DateTimeImmutable $now): bool;

    public function appendStatusEvent(int $accountId, string $eventType, DateTimeImmutable $now): void;

    public function revokeActiveAccess(int $accountId, DateTimeImmutable $now): void;

    public function findCompletedSubmission(UuidV7 $submissionId, string $fingerprint): ?AccountStateOperationResult;

    public function record(
        UuidV7 $operationId,
        AccountStateOperationCommand $command,
        int $targetInternalId,
        int $stepUpGrantId,
        string $auditEventPublicId,
        int $versionBefore,
        DateTimeImmutable $now,
    ): void;
}
