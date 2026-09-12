<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionScoring\Application;

use DateTimeImmutable;
use Qmdb\Shared\Identifier\UuidV7;

/** Workspace-scoped persistence for judge-owned draft score sheets. */
interface CompetitionScoreSheetRepository
{
    /** @return array{id:int,round_id:int,round_status:string,judge_id:int,assignment_role:string}|null */
    public function lockAcceptedAssignment(int $workspaceId, int $accountId, UuidV7 $assignmentPublicId): ?array;

    /** @return array{id:int,public_id:string,version:int,status:string}|null */
    public function lockDraft(int $workspaceId, int $assignmentId, UuidV7 $participantPublicId): ?array;

    /** @return array{id:int,aggregation_method:string}|null */
    public function activeRubricForRound(int $workspaceId, int $roundId): ?array;

    /** @return list<array{criterion_code:string,direction:string,min_units:int,max_units:int,step_units:int,weight_basis_points:int}> */
    public function criteria(int $workspaceId, int $rubricId): array;

    /**
     * @param array{id:int,public_id:string,version:int,status:string}|null $existing
     * @param array<string,int> $rawUnits
     * @param array<string,int> $weightedUnits
     * @return array{public_id:string,version:int,status:string}
     */
    public function saveDraft(int $workspaceId, int $roundId, int $assignmentId, int $rubricId, UuidV7 $participantPublicId, ?array $existing, array $rawUnits, array $weightedUnits, int $totalUnits, int $penaltyUnits, DateTimeImmutable $now): array;

    /** @return array{public_id:string,version:int,status:string} */
    public function prepareForLock(int $workspaceId, int $accountId, UuidV7 $scoreSheetPublicId, DateTimeImmutable $now): array;
}
