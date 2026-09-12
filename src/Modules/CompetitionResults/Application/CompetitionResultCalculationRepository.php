<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionResults\Application;

use DateTimeImmutable;
use Qmdb\Modules\CompetitionResults\Domain\CalculatedCompetitionResult;
use Qmdb\Shared\Identifier\UuidV7;

/** Persistence boundary for locked-sheet result calculation only. */
interface CompetitionResultCalculationRepository
{
    /** @return array{id:int,public_id:string,workspace_id:int,status:string,version:int}|null */
    public function lockRound(int $workspaceId, UuidV7 $roundPublicId): ?array;

    /** @return array{rubric_id:int,aggregation_method:string,locked_scores:list<array{participant_public_id:string,public_label:string,total_units:int,score_sheet_checksum:string}>,tie_break_rules:list<array{basis:string,direction:string}>} */
    public function lockedCalculationInput(int $workspaceId, int $roundId): array;

    /** @return array{public_id:string,status:string}|null */
    public function resultForInput(int $workspaceId, int $roundId, string $inputChecksum): ?array;

    /** @return array{public_id:string,status:string} */
    public function persistCalculatedResult(int $workspaceId, int $roundId, int $rubricId, int $actorAccountId, CalculatedCompetitionResult $result, DateTimeImmutable $now): array;
}
