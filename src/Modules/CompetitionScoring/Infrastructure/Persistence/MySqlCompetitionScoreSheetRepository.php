<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionScoring\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Qmdb\Modules\CompetitionScoring\Application\CompetitionScoreSheetRepository;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class MySqlCompetitionScoreSheetRepository implements CompetitionScoreSheetRepository
{
    public function __construct(private DatabaseConnectionProvider $connections)
    {
    }

    public function lockAcceptedAssignment(int $workspaceId, int $accountId, UuidV7 $assignmentPublicId): ?array
    {
        $statement = $this->connections->connection()->prepare('SELECT assignment.id, panel.round_id, round_record.status AS round_status, judge.id AS judge_id, assignment.assignment_role FROM competition_judge_assignments assignment INNER JOIN competition_judge_panels panel ON panel.workspace_id = assignment.workspace_id AND panel.id = assignment.panel_id INNER JOIN competition_rounds round_record ON round_record.workspace_id = panel.workspace_id AND round_record.id = panel.round_id INNER JOIN competition_judges judge ON judge.workspace_id = assignment.workspace_id AND judge.id = assignment.judge_id WHERE assignment.workspace_id = :workspace_id AND assignment.public_id = :assignment_public_id AND assignment.status = \'ACCEPTED\' AND assignment.assignment_role IN (\'HEAD_JUDGE\',\'JUDGE\') AND judge.account_id = :account_id AND judge.status = \'ACTIVE\' FOR UPDATE');
        $statement->execute([':workspace_id' => $workspaceId, ':assignment_public_id' => $assignmentPublicId->toBinary(), ':account_id' => $accountId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        return ['id' => self::requiredInt($row, 'id'), 'round_id' => self::requiredInt($row, 'round_id'), 'round_status' => self::requiredString($row, 'round_status'), 'judge_id' => self::requiredInt($row, 'judge_id'), 'assignment_role' => self::requiredString($row, 'assignment_role')];
    }

    public function lockDraft(int $workspaceId, int $assignmentId, UuidV7 $participantPublicId): ?array
    {
        $statement = $this->connections->connection()->prepare('SELECT score_sheet.id, score_sheet.public_id, score_sheet.version, score_sheet.status FROM competition_score_sheets score_sheet INNER JOIN competition_round_participants participant ON participant.workspace_id = score_sheet.workspace_id AND participant.id = score_sheet.participant_id WHERE score_sheet.workspace_id = :workspace_id AND score_sheet.assignment_id = :assignment_id AND participant.public_id = :participant_public_id AND score_sheet.status = \'DRAFT\' FOR UPDATE');
        $statement->execute([':workspace_id' => $workspaceId, ':assignment_id' => $assignmentId, ':participant_public_id' => $participantPublicId->toBinary()]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        return ['id' => self::requiredInt($row, 'id'), 'public_id' => UuidV7::fromBinary(self::requiredString($row, 'public_id'))->toString(), 'version' => self::requiredInt($row, 'version'), 'status' => self::requiredString($row, 'status')];
    }

    public function activeRubricForRound(int $workspaceId, int $roundId): ?array
    {
        $statement = $this->connections->connection()->prepare('SELECT rubric.id, rubric.aggregation_method FROM competition_scoring_rubrics rubric INNER JOIN competition_rounds round_record ON round_record.workspace_id = rubric.workspace_id AND round_record.category_id = rubric.category_id WHERE rubric.workspace_id = :workspace_id AND round_record.id = :round_id AND rubric.status = \'ACTIVE\' LIMIT 1 FOR UPDATE');
        $statement->execute([':workspace_id' => $workspaceId, ':round_id' => $roundId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        return ['id' => self::requiredInt($row, 'id'), 'aggregation_method' => self::requiredString($row, 'aggregation_method')];
    }

    public function criteria(int $workspaceId, int $rubricId): array
    {
        $statement = $this->connections->connection()->prepare('SELECT criterion_code, direction, min_units, max_units, step_units, weight_basis_points FROM competition_scoring_criteria WHERE workspace_id = :workspace_id AND rubric_id = :rubric_id ORDER BY display_order ASC, id ASC');
        $statement->execute([':workspace_id' => $workspaceId, ':rubric_id' => $rubricId]);
        $criteria = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                throw new \UnexpectedValueException('Scoring criterion data has an invalid row.');
            }
            $criteria[] = ['criterion_code' => self::requiredString($row, 'criterion_code'), 'direction' => self::requiredString($row, 'direction'), 'min_units' => self::requiredInt($row, 'min_units'), 'max_units' => self::requiredInt($row, 'max_units'), 'step_units' => self::requiredInt($row, 'step_units'), 'weight_basis_points' => self::requiredInt($row, 'weight_basis_points')];
        }

        return $criteria;
    }

    /** @param array{id:int,public_id:string,version:int,status:string}|null $existing @param array<string,int> $rawUnits @param array<string,int> $weightedUnits @return array{public_id:string,version:int,status:string} */
    public function saveDraft(int $workspaceId, int $roundId, int $assignmentId, int $rubricId, UuidV7 $participantPublicId, ?array $existing, array $rawUnits, array $weightedUnits, int $totalUnits, int $penaltyUnits, DateTimeImmutable $now): array
    {
        $pdo = $this->connections->connection();
        $time = self::time($now);
        $participant = $pdo->prepare('SELECT id FROM competition_round_participants WHERE workspace_id = :workspace_id AND round_id = :round_id AND public_id = :participant_public_id AND status IN (\'SEEDED\',\'ACTIVE\') FOR UPDATE');
        $participant->execute([':workspace_id' => $workspaceId, ':round_id' => $roundId, ':participant_public_id' => $participantPublicId->toBinary()]);
        $participantId = $participant->fetchColumn();
        if (!is_string($participantId) && !is_int($participantId)) {
            throw new \DomainException('Competition participant is unavailable.');
        }
        $participantId = self::databaseInt($participantId, 'participant identifier');
        if ($existing === null) {
            $publicId = UuidV7::generate();
            $insert = $pdo->prepare('INSERT INTO competition_score_sheets (public_id, workspace_id, round_id, participant_id, assignment_id, rubric_id, status, total_units, penalty_units, checksum_sha256, supersedes_score_sheet_id, version, submitted_at, locked_at, created_at, updated_at) VALUES (:public_id,:workspace_id,:round_id,:participant_id,:assignment_id,:rubric_id,\'DRAFT\',:total_units,:penalty_units,NULL,NULL,1,NULL,NULL,:now,:now)');
            $insert->execute([':public_id' => $publicId->toBinary(), ':workspace_id' => $workspaceId, ':round_id' => $roundId, ':participant_id' => $participantId, ':assignment_id' => $assignmentId, ':rubric_id' => $rubricId, ':total_units' => $totalUnits, ':penalty_units' => $penaltyUnits, ':now' => $time]);
            $sheetId = (int) $pdo->lastInsertId();
            $version = 1;
        } else {
            $sheetId = $existing['id'];
            $publicId = UuidV7::fromString($existing['public_id']);
            $version = $existing['version'] + 1;
            $update = $pdo->prepare('UPDATE competition_score_sheets SET total_units = :total_units, penalty_units = :penalty_units, checksum_sha256 = NULL, version = :version, updated_at = :now WHERE id = :id AND workspace_id = :workspace_id AND status = \'DRAFT\' AND version = :previous_version');
            $update->execute([':total_units' => $totalUnits, ':penalty_units' => $penaltyUnits, ':version' => $version, ':now' => $time, ':id' => $sheetId, ':workspace_id' => $workspaceId, ':previous_version' => $existing['version']]);
            if ($update->rowCount() !== 1) {
                throw new \DomainException('Score-sheet draft is stale.');
            }
            $delete = $pdo->prepare('DELETE FROM competition_score_entries WHERE workspace_id = :workspace_id AND score_sheet_id = :score_sheet_id');
            $delete->execute([':workspace_id' => $workspaceId, ':score_sheet_id' => $sheetId]);
        }
        $criteria = $pdo->prepare('SELECT id, criterion_code FROM competition_scoring_criteria WHERE workspace_id = :workspace_id AND rubric_id = :rubric_id ORDER BY display_order ASC, id ASC');
        $criteria->execute([':workspace_id' => $workspaceId, ':rubric_id' => $rubricId]);
        $insertEntry = $pdo->prepare('INSERT INTO competition_score_entries (public_id, workspace_id, score_sheet_id, criterion_id, raw_units, weighted_units, created_at, updated_at) VALUES (:public_id,:workspace_id,:score_sheet_id,:criterion_id,:raw_units,:weighted_units,:now,:now)');
        foreach ($criteria->fetchAll(PDO::FETCH_ASSOC) as $criterion) {
            if (!is_array($criterion)) {
                throw new \UnexpectedValueException('Scoring criterion data has an invalid row.');
            }
            $criterionCode = self::requiredString($criterion, 'criterion_code');
            if (!isset($rawUnits[$criterionCode], $weightedUnits[$criterionCode])) {
                throw new \DomainException('Score criteria do not match the active rubric.');
            }
            $insertEntry->execute([':public_id' => UuidV7::generate()->toBinary(), ':workspace_id' => $workspaceId, ':score_sheet_id' => $sheetId, ':criterion_id' => self::requiredInt($criterion, 'id'), ':raw_units' => $rawUnits[$criterionCode], ':weighted_units' => $weightedUnits[$criterionCode], ':now' => $time]);
        }

        return ['public_id' => $publicId->toString(), 'version' => $version, 'status' => 'DRAFT'];
    }

    public function prepareForLock(int $workspaceId, int $accountId, UuidV7 $scoreSheetPublicId, DateTimeImmutable $now): array
    {
        $pdo = $this->connections->connection();
        $sheet = $pdo->prepare('SELECT score_sheet.id, score_sheet.public_id, score_sheet.version, score_sheet.status, score_sheet.total_units, score_sheet.penalty_units FROM competition_score_sheets score_sheet INNER JOIN competition_judge_assignments assignment ON assignment.workspace_id = score_sheet.workspace_id AND assignment.id = score_sheet.assignment_id INNER JOIN competition_judges judge ON judge.workspace_id = assignment.workspace_id AND judge.id = assignment.judge_id WHERE score_sheet.workspace_id = :workspace_id AND score_sheet.public_id = :public_id AND score_sheet.status = \'SUBMITTED\' AND assignment.status = \'ACCEPTED\' AND judge.account_id = :account_id AND judge.status = \'ACTIVE\' FOR UPDATE');
        $sheet->execute([':workspace_id' => $workspaceId, ':public_id' => $scoreSheetPublicId->toBinary(), ':account_id' => $accountId]);
        $row = $sheet->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new \DomainException('Submitted score sheet is unavailable.');
        }
        $entries = $pdo->prepare('SELECT criterion.criterion_code, entry.raw_units, entry.weighted_units FROM competition_score_entries entry INNER JOIN competition_scoring_criteria criterion ON criterion.workspace_id = entry.workspace_id AND criterion.id = entry.criterion_id WHERE entry.workspace_id = :workspace_id AND entry.score_sheet_id = :score_sheet_id ORDER BY criterion.criterion_code ASC, entry.id ASC');
        $sheetId = self::requiredInt($row, 'id');
        $entries->execute([':workspace_id' => $workspaceId, ':score_sheet_id' => $sheetId]);
        $evidence = [];
        foreach ($entries->fetchAll(PDO::FETCH_ASSOC) as $entry) {
            if (!is_array($entry)) {
                throw new \UnexpectedValueException('Score-entry data has an invalid row.');
            }
            $evidence[] = ['criterion_code' => self::requiredString($entry, 'criterion_code'), 'raw_units' => self::requiredInt($entry, 'raw_units'), 'weighted_units' => self::requiredInt($entry, 'weighted_units')];
        }
        if ($evidence === []) {
            throw new \DomainException('Score sheet has no criterion evidence.');
        }
        $checksum = hash('sha256', json_encode(['entries' => $evidence, 'penalty_units' => self::requiredInt($row, 'penalty_units'), 'total_units' => self::requiredInt($row, 'total_units')], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), true);
        $update = $pdo->prepare('UPDATE competition_score_sheets SET checksum_sha256 = :checksum, updated_at = :now WHERE id = :id AND workspace_id = :workspace_id AND version = :version AND status = \'SUBMITTED\'');
        $update->execute([':checksum' => $checksum, ':now' => self::time($now), ':id' => $sheetId, ':workspace_id' => $workspaceId, ':version' => self::requiredInt($row, 'version')]);
        if ($update->rowCount() !== 1) {
            throw new \DomainException('Score sheet changed before locking.');
        }

        return ['public_id' => UuidV7::fromBinary(self::requiredString($row, 'public_id'))->toString(), 'version' => self::requiredInt($row, 'version'), 'status' => 'SUBMITTED'];
    }

    private static function time(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }

    /** @param array<mixed,mixed> $row */
    private static function requiredString(array $row, string $column): string
    {
        $value = $row[$column] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \UnexpectedValueException(sprintf('Database column "%s" is missing or invalid.', $column));
        }

        return $value;
    }

    /** @param array<mixed,mixed> $row */
    private static function requiredInt(array $row, string $column): int
    {
        return self::databaseInt($row[$column] ?? null, $column);
    }

    private static function databaseInt(mixed $value, string $column): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/\\A(?:0|[1-9][0-9]*)\\z/', $value) === 1) {
            return (int) $value;
        }

        throw new \UnexpectedValueException(sprintf('Database column "%s" is missing or not an integer.', $column));
    }
}
