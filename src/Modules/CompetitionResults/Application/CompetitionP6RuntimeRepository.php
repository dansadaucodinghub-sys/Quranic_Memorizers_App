<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionResults\Application;

use DateTimeImmutable;
use Qmdb\Shared\Identifier\UuidV7;

/**
 * Narrow persistence boundary for authoritative P6 lifecycle transitions.
 * All methods are workspace-scoped; callers never supply SQL or a table name.
 */
interface CompetitionP6RuntimeRepository
{
    /** @return array{id:int,public_id:string,workspace_id:int,status:string,version:int,round_id?:int,judge_account_id?:int}|null */
    public function lock(string $aggregateKind, int $workspaceId, UuidV7 $publicId): ?array;

    /** @return array{status:string,version:int}|null */
    public function completed(UuidV7 $submissionId, string $fingerprint): ?array;

    /** @param array{id:int,public_id:string,workspace_id:int,status:string,version:int,round_id?:int,judge_account_id?:int} $aggregate */
    public function transition(string $aggregateKind, array $aggregate, string $targetStatus, int $actorAccountId, DateTimeImmutable $now): bool;

    /**
     * @param array{id:int,public_id:string,workspace_id:int,status:string,version:int,round_id?:int,judge_account_id?:int} $aggregate
     * @param array<string, scalar|null> $safeMetadata
     */
    public function appendEvent(string $aggregateKind, array $aggregate, string $operationCode, int $actorAccountId, array $safeMetadata, DateTimeImmutable $now): void;

    /** @param array{id:int,public_id:string,workspace_id:int,status:string,version:int,round_id?:int,judge_account_id?:int} $aggregate */
    public function record(UuidV7 $submissionId, string $fingerprint, string $operationCode, array $aggregate, string $status, int $versionAfter, DateTimeImmutable $now): void;

    /**
     * @param array{id:int,public_id:string,workspace_id:int,status:string,version:int,round_id?:int,judge_account_id?:int} $aggregate
     * @param array<string, scalar|null> $safePayload
     */
    public function notificationIntent(array $aggregate, ?int $accountId, string $intentType, array $safePayload, DateTimeImmutable $now): void;

    /** @return list<array{edition_slug:string,category_slug:string,round_code:string,public_id:string,rank_position:int,total_units:int,public_label:string,published_at:string}> */
    public function publicResults(string $editionSlug, ?string $categorySlug, ?string $roundCode, int $limit): array;
}
