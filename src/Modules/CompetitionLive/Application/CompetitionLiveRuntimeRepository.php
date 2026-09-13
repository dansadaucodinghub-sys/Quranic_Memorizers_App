<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionLive\Application;

use DateTimeImmutable;
use Qmdb\Shared\Identifier\UuidV7;

/** Persistence boundary for P7 authoritative mutations only. */
interface CompetitionLiveRuntimeRepository
{
    /** @return array{id:int,public_id:string,workspace_id:int,status:string,head_sequence:int,version:int}|null */
    public function lockSession(int $workspaceId, UuidV7 $sessionPublicId): ?array;

    /** @return array{id:int,public_id:string,workspace_id:int,live_session_id:int,current_state:string,call_sequence:int,state_version:int,version:int}|null */
    public function lockParticipant(int $workspaceId, UuidV7 $participantPublicId): ?array;

    /** @return array{status:string,version:int,sequence:int}|null */
    public function completed(UuidV7 $submissionId, string $requestFingerprint): ?array;

    /** @param array{id:int,public_id:string,workspace_id:int,status:string,head_sequence:int,version:int} $session */
    public function transitionSession(array $session, string $targetStatus, DateTimeImmutable $now): bool;

    /** @param array{id:int,public_id:string,workspace_id:int,live_session_id:int,current_state:string,call_sequence:int,state_version:int,version:int} $participant */
    public function transitionParticipant(array $participant, string $targetState, DateTimeImmutable $now): bool;

    /** @param array{id:int,public_id:string,workspace_id:int,status:string,head_sequence:int,version:int} $session
     * @param array<string, scalar|array<array-key, scalar|null>|null> $payload
     */
    public function appendEvent(array $session, string $eventType, string $visibility, array $payload, ?int $actorAccountId, UuidV7 $correlationId, DateTimeImmutable $now): int;

    /** @param array{id:int,public_id:string,workspace_id:int,status:string,head_sequence:int,version:int} $session */
    public function record(UuidV7 $submissionId, string $requestFingerprint, string $operation, array $session, string $status, int $versionAfter, int $sequence, DateTimeImmutable $now): void;

    /** @param array{id:int,public_id:string,workspace_id:int,live_session_id:int,current_state:string,call_sequence:int,state_version:int,version:int} $participant */
    public function recordParticipant(UuidV7 $submissionId, string $requestFingerprint, string $operation, array $participant, string $state, int $versionAfter, int $sequence, DateTimeImmutable $now): void;
}
