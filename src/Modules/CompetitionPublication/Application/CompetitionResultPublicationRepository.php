<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionPublication\Application;

use DateTimeImmutable;
use Qmdb\Shared\Identifier\UuidV7;

/** Persistence boundary for authoritative P7 result-publication mutations. */
interface CompetitionResultPublicationRepository
{
    /** @return array{id:int,public_id:string,workspace_id:int,round_id:int,result_run_id:int,status:string,version:int}|null */
    public function lockPublication(int $workspaceId, UuidV7 $publicationId): ?array;

    /** @return array{id:int,public_id:string,workspace_id:int,round_id:int,status:string,input_checksum:string,result_checksum:string}|null */
    public function lockPublishedResultRun(int $workspaceId, UuidV7 $resultRunId): ?array;

    /** @return array{publication_id:string,status:string,version:int}|null */
    public function completed(UuidV7 $submissionId, string $fingerprint): ?array;

    /** @param array{id:int,public_id:string,workspace_id:int,round_id:int,status:string,input_checksum:string,result_checksum:string} $resultRun
     * @return array{id:int,public_id:string,workspace_id:int,round_id:int,result_run_id:int,status:string,version:int}
     */
    public function prepare(array $resultRun, int $actorAccountId, DateTimeImmutable $now): array;

    /** @param array{id:int,public_id:string,workspace_id:int,round_id:int,result_run_id:int,status:string,version:int} $publication */
    public function transition(array $publication, string $target, int $actorAccountId, DateTimeImmutable $now): bool;

    /** @param array{id:int,public_id:string,workspace_id:int,round_id:int,result_run_id:int,status:string,version:int} $publication */
    public function appendEvent(array $publication, string $eventType, ?int $actorAccountId, DateTimeImmutable $now): void;

    /** @param array{id:int,public_id:string,workspace_id:int,round_id:int,result_run_id:int,status:string,version:int} $publication */
    public function record(UuidV7 $submissionId, string $fingerprint, string $operation, array $publication, string $status, int $versionAfter, DateTimeImmutable $now): void;
}
