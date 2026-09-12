<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionResults\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Qmdb\Modules\CompetitionResults\Application\CompetitionResultCalculationRepository;
use Qmdb\Modules\CompetitionResults\Domain\CalculatedCompetitionResult;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class MySqlCompetitionResultCalculationRepository implements CompetitionResultCalculationRepository
{
    public function __construct(private DatabaseConnectionProvider $connections)
    {
    }

    public function lockRound(int $workspaceId, UuidV7 $roundPublicId): ?array
    {
        $statement = $this->connections->connection()->prepare('SELECT id, public_id, workspace_id, status, version FROM competition_rounds WHERE workspace_id = :workspace_id AND public_id = :public_id FOR UPDATE');
        $statement->execute([':workspace_id' => $workspaceId, ':public_id' => $roundPublicId->toBinary()]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        return [
            'id' => self::requiredInt($row, 'id'),
            'public_id' => UuidV7::fromBinary(self::requiredBinary($row, 'public_id'))->toString(),
            'workspace_id' => self::requiredInt($row, 'workspace_id'),
            'status' => self::requiredString($row, 'status'),
            'version' => self::requiredInt($row, 'version'),
        ];
    }

    public function lockedCalculationInput(int $workspaceId, int $roundId): array
    {
        $statement = $this->connections->connection()->prepare('SELECT score_sheet.rubric_id, rubric.aggregation_method, participant.public_id AS participant_public_id, participant.participant_code AS public_label, score_sheet.total_units, score_sheet.checksum_sha256 FROM competition_score_sheets score_sheet INNER JOIN competition_round_participants participant ON participant.workspace_id = score_sheet.workspace_id AND participant.id = score_sheet.participant_id INNER JOIN competition_scoring_rubrics rubric ON rubric.workspace_id = score_sheet.workspace_id AND rubric.id = score_sheet.rubric_id WHERE score_sheet.workspace_id = :workspace_id AND score_sheet.round_id = :round_id AND score_sheet.status = \'LOCKED\' AND participant.status NOT IN (\'WITHDRAWN\',\'CANCELLED\') ORDER BY participant.id ASC, score_sheet.id ASC FOR UPDATE');
        $statement->execute([':workspace_id' => $workspaceId, ':round_id' => $roundId]);
        $records = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($records === []) {
            throw new \DomainException('No locked score sheets are available for this round.');
        }
        $first = $records[0];
        if (!is_array($first)) {
            throw new \UnexpectedValueException('Locked score-sheet data has an invalid row.');
        }
        $rubricId = self::requiredInt($first, 'rubric_id');
        $method = self::requiredString($first, 'aggregation_method');
        $scores = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                throw new \UnexpectedValueException('Locked score-sheet data has an invalid row.');
            }
            $checksum = self::requiredBinary($record, 'checksum_sha256');
            if (self::requiredInt($record, 'rubric_id') !== $rubricId || strlen($checksum) !== 32) {
                throw new \DomainException('Locked score-sheet evidence is inconsistent.');
            }
            $scores[] = [
                'participant_public_id' => UuidV7::fromBinary(self::requiredBinary($record, 'participant_public_id'))->toString(),
                'public_label' => self::requiredString($record, 'public_label'),
                'total_units' => self::requiredInt($record, 'total_units'),
                'score_sheet_checksum' => bin2hex($checksum),
            ];
        }
        $tie = $this->connections->connection()->prepare('SELECT basis, direction FROM competition_tie_break_rules WHERE workspace_id = :workspace_id AND rubric_id = :rubric_id ORDER BY rule_order ASC, id ASC');
        $tie->execute([':workspace_id' => $workspaceId, ':rubric_id' => $rubricId]);
        $rules = [];
        foreach ($tie->fetchAll(PDO::FETCH_ASSOC) as $rule) {
            if (!is_array($rule)) {
                throw new \UnexpectedValueException('Tie-break data has an invalid row.');
            }
            $rules[] = ['basis' => self::requiredString($rule, 'basis'), 'direction' => self::requiredString($rule, 'direction')];
        }

        return ['rubric_id' => $rubricId, 'aggregation_method' => $method, 'locked_scores' => $scores, 'tie_break_rules' => $rules];
    }

    public function resultForInput(int $workspaceId, int $roundId, string $inputChecksum): ?array
    {
        $statement = $this->connections->connection()->prepare('SELECT public_id, status FROM competition_result_runs WHERE workspace_id = :workspace_id AND round_id = :round_id AND input_checksum_sha256 = :input_checksum LIMIT 1 FOR UPDATE');
        $statement->execute([':workspace_id' => $workspaceId, ':round_id' => $roundId, ':input_checksum' => hex2bin($inputChecksum)]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        return ['public_id' => UuidV7::fromBinary(self::requiredBinary($row, 'public_id'))->toString(), 'status' => self::requiredString($row, 'status')];
    }

    public function persistCalculatedResult(int $workspaceId, int $roundId, int $rubricId, int $actorAccountId, CalculatedCompetitionResult $result, DateTimeImmutable $now): array
    {
        $pdo = $this->connections->connection();
        $runId = UuidV7::generate();
        $at = $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
        $run = $pdo->prepare('INSERT INTO competition_result_runs (public_id, workspace_id, round_id, rubric_id, status, input_checksum_sha256, result_checksum_sha256, supersedes_result_run_id, calculated_by_account_id, calculated_at) VALUES (:public_id,:workspace_id,:round_id,:rubric_id,\'CALCULATED\',:input_checksum,:result_checksum,NULL,:actor_account_id,:calculated_at)');
        $run->execute([':public_id' => $runId->toBinary(), ':workspace_id' => $workspaceId, ':round_id' => $roundId, ':rubric_id' => $rubricId, ':input_checksum' => hex2bin($result->inputChecksum), ':result_checksum' => hex2bin($result->resultChecksum), ':actor_account_id' => $actorAccountId, ':calculated_at' => $at]);
        $runInternalId = (int) $pdo->lastInsertId();
        $participant = $pdo->prepare('SELECT id FROM competition_round_participants WHERE workspace_id = :workspace_id AND public_id = :public_id LIMIT 1');
        $insert = $pdo->prepare('INSERT INTO competition_result_rows (public_id, workspace_id, result_run_id, participant_id, rank_position, total_units, disqualified, public_label, row_checksum_sha256, created_at) VALUES (:public_id,:workspace_id,:result_run_id,:participant_id,:rank_position,:total_units,0,:public_label,:row_checksum,:created_at)');
        foreach ($result->rows as $row) {
            $participant->execute([':workspace_id' => $workspaceId, ':public_id' => UuidV7::fromString($row['participantPublicId'])->toBinary()]);
            $participantId = $participant->fetchColumn();
            if (!is_string($participantId) && !is_int($participantId)) {
                throw new \DomainException('A result participant is unavailable.');
            }
            $participantId = self::databaseInt($participantId, 'participant identifier');
            $checksum = hash('sha256', json_encode(['run' => $runId->toString(), 'participant' => $row['participantPublicId'], 'rank' => $row['rank'], 'total_units' => $row['totalUnits'], 'public_label' => $row['publicLabel']], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), true);
            $insert->execute([':public_id' => UuidV7::generate()->toBinary(), ':workspace_id' => $workspaceId, ':result_run_id' => $runInternalId, ':participant_id' => $participantId, ':rank_position' => $row['rank'], ':total_units' => $row['totalUnits'], ':public_label' => $row['publicLabel'], ':row_checksum' => $checksum, ':created_at' => $at]);
        }

        return ['public_id' => $runId->toString(), 'status' => 'CALCULATED'];
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
    private static function requiredBinary(array $row, string $column): string
    {
        return self::requiredString($row, $column);
    }

    /** @param array<mixed,mixed> $row */
    private static function requiredInt(array $row, string $column): int
    {
        $value = $row[$column] ?? null;

        return self::databaseInt($value, $column);
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
