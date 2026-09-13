<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionResults\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOStatement;
use Qmdb\Modules\CompetitionResults\Application\CompetitionP6RuntimeRepository;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Schema\State\PdoResultReader;

final readonly class MySqlCompetitionP6RuntimeRepository implements CompetitionP6RuntimeRepository
{
    public function __construct(private DatabaseConnectionProvider $connections)
    {
    }

    public function lock(string $aggregateKind, int $workspaceId, UuidV7 $publicId): ?array
    {
        $query = match ($aggregateKind) {
            'ROUND' => 'SELECT id, public_id, workspace_id, status, version FROM competition_rounds WHERE workspace_id = :workspace_id AND public_id = :public_id FOR UPDATE',
            'ASSIGNMENT' => 'SELECT assignment.id, assignment.public_id, assignment.workspace_id, assignment.status, assignment.version, panel.round_id, judge.account_id AS judge_account_id FROM competition_judge_assignments assignment INNER JOIN competition_judge_panels panel ON panel.workspace_id = assignment.workspace_id AND panel.id = assignment.panel_id INNER JOIN competition_judges judge ON judge.workspace_id = assignment.workspace_id AND judge.id = assignment.judge_id WHERE assignment.workspace_id = :workspace_id AND assignment.public_id = :public_id FOR UPDATE',
            'SCORE_SHEET' => 'SELECT score_sheet.id, score_sheet.public_id, score_sheet.workspace_id, score_sheet.status, score_sheet.version, score_sheet.round_id FROM competition_score_sheets score_sheet INNER JOIN competition_judge_assignments assignment ON assignment.workspace_id = score_sheet.workspace_id AND assignment.id = score_sheet.assignment_id INNER JOIN competition_judges judge ON judge.workspace_id = assignment.workspace_id AND judge.id = assignment.judge_id INNER JOIN competition_rounds round_record ON round_record.workspace_id = score_sheet.workspace_id AND round_record.id = score_sheet.round_id WHERE score_sheet.workspace_id = :workspace_id AND score_sheet.public_id = :public_id AND (score_sheet.status <> \'SUBMITTED\' OR (assignment.status = \'ACCEPTED\' AND assignment.assignment_role IN (\'HEAD_JUDGE\',\'JUDGE\') AND judge.status = \'ACTIVE\' AND round_record.status = \'SCORING_OPEN\' AND NOT EXISTS (SELECT 1 FROM competition_judge_conflicts conflict WHERE conflict.workspace_id = score_sheet.workspace_id AND conflict.assignment_id = score_sheet.assignment_id AND conflict.status IN (\'DECLARED\',\'RECUSED\') AND (conflict.participant_id IS NULL OR conflict.participant_id = score_sheet.participant_id)))) FOR UPDATE',
            'RESULT_RUN' => 'SELECT id, public_id, workspace_id, status, 1 AS version, round_id FROM competition_result_runs WHERE workspace_id = :workspace_id AND public_id = :public_id FOR UPDATE',
            'APPEAL' => 'SELECT id, public_id, workspace_id, status, version FROM competition_appeals WHERE workspace_id = :workspace_id AND public_id = :public_id FOR UPDATE',
            default => throw new \InvalidArgumentException('P6 aggregate kind is invalid.'),
        };
        $statement = $this->connections->connection()->prepare($query);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Competition aggregate lock statement could not be prepared.');
        }
        $statement->bindValue(':workspace_id', $workspaceId, PDO::PARAM_INT);
        $statement->bindValue(':public_id', $publicId->toBinary(), PDO::PARAM_LOB);
        $statement->execute();
        $row = self::row($statement->fetch(PDO::FETCH_ASSOC));
        if ($row === null) {
            return null;
        }

        $result = [
            'id' => PdoResultReader::integer($row, 'id'),
            'public_id' => UuidV7::fromBinary(PdoResultReader::string($row, 'public_id'))->toString(),
            'workspace_id' => PdoResultReader::integer($row, 'workspace_id'),
            'status' => PdoResultReader::string($row, 'status'),
            'version' => PdoResultReader::integer($row, 'version'),
        ];
        if (isset($row['round_id'])) {
            $result['round_id'] = PdoResultReader::integer($row, 'round_id');
        }
        if (isset($row['judge_account_id'])) {
            $result['judge_account_id'] = PdoResultReader::integer($row, 'judge_account_id');
        }

        return $result;
    }

    public function completed(UuidV7 $submissionId, string $fingerprint): ?array
    {
        $statement = $this->connections->connection()->prepare('SELECT request_fingerprint, result_status, version_after FROM competition_p6_operations WHERE submission_id = :submission_id FOR UPDATE');
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Competition operation lookup statement could not be prepared.');
        }
        $statement->execute([':submission_id' => $submissionId->toBinary()]);
        $row = self::row($statement->fetch(PDO::FETCH_ASSOC));
        if ($row === null) {
            return null;
        }
        if (!hash_equals(PdoResultReader::string($row, 'request_fingerprint'), $fingerprint)) {
            throw new \DomainException('Competition submission conflicts with a prior request.');
        }

        return ['status' => PdoResultReader::string($row, 'result_status'), 'version' => PdoResultReader::integer($row, 'version_after')];
    }

    public function transition(string $aggregateKind, array $aggregate, string $targetStatus, int $actorAccountId, DateTimeImmutable $now): bool
    {
        $time = self::time($now);
        if ($aggregateKind === 'RESULT_RUN') {
            $fields = ['status = :status'];
            $parameters = [':status' => $targetStatus, ':id' => $aggregate['id'], ':workspace_id' => $aggregate['workspace_id'], ':previous' => $aggregate['status']];
            if ($targetStatus === 'PUBLISHED') {
                $roundId = $aggregate['round_id'] ?? null;
                if (!is_int($roundId)) {
                    throw new \RuntimeException('Result run has no governed round.');
                }
                $supersede = $this->connections->connection()->prepare('UPDATE competition_result_runs SET status = \'SUPERSEDED\' WHERE workspace_id = :workspace_id AND round_id = :round_id AND status = \'PUBLISHED\' AND id <> :id');
                if (!$supersede instanceof PDOStatement) {
                    throw new \RuntimeException('Result supersession statement could not be prepared.');
                }
                $supersede->execute([':workspace_id' => $aggregate['workspace_id'], ':round_id' => $roundId, ':id' => $aggregate['id']]);
                $fields[] = 'published_by_account_id = :actor';
                $fields[] = 'published_at = :now';
                $parameters[':actor'] = $actorAccountId;
                $parameters[':now'] = $time;
            }
            if ($targetStatus === 'VERIFIED') {
                $fields[] = 'verified_by_account_id = :actor';
                $fields[] = 'verified_at = :now';
                $parameters[':actor'] = $actorAccountId;
                $parameters[':now'] = $time;
            }
            $statement = $this->connections->connection()->prepare('UPDATE competition_result_runs SET ' . implode(', ', $fields) . ' WHERE id = :id AND workspace_id = :workspace_id AND status = :previous');
            $statement->execute($parameters);

            return $statement->rowCount() === 1;
        }
        $statement = match ($aggregateKind) {
            'ROUND' => $this->connections->connection()->prepare('UPDATE competition_rounds SET status = :status, version = version + 1, opens_at = CASE WHEN :status = \'SCORING_OPEN\' THEN :now ELSE opens_at END, closes_at = CASE WHEN :status = \'SCORING_CLOSED\' THEN :now ELSE closes_at END, updated_at = :now WHERE id = :id AND workspace_id = :workspace_id AND status = :previous AND version = :version'),
            'ASSIGNMENT' => $this->connections->connection()->prepare('UPDATE competition_judge_assignments SET status = :status, version = version + 1, accepted_at = CASE WHEN :status = \'ACCEPTED\' THEN :now ELSE accepted_at END, revoked_at = CASE WHEN :status = \'REVOKED\' THEN :now ELSE revoked_at END, updated_at = :now WHERE id = :id AND workspace_id = :workspace_id AND status = :previous AND version = :version'),
            'SCORE_SHEET' => $this->connections->connection()->prepare('UPDATE competition_score_sheets SET status = :status, version = version + 1, submitted_at = CASE WHEN :status = \'SUBMITTED\' THEN :now ELSE submitted_at END, locked_at = CASE WHEN :status = \'LOCKED\' THEN :now ELSE locked_at END, updated_at = :now WHERE id = :id AND workspace_id = :workspace_id AND status = :previous AND version = :version'),
            'APPEAL' => $this->connections->connection()->prepare('UPDATE competition_appeals SET status = :status, version = version + 1, decided_at = CASE WHEN :status IN (\'UPHELD\', \'DISMISSED\') THEN :now ELSE decided_at END WHERE id = :id AND workspace_id = :workspace_id AND status = :previous AND version = :version'),
            default => throw new \InvalidArgumentException('P6 aggregate kind is invalid.'),
        };
        $parameters = [':status' => $targetStatus, ':id' => $aggregate['id'], ':workspace_id' => $aggregate['workspace_id'], ':previous' => $aggregate['status'], ':version' => $aggregate['version']];
        $parameters[':now'] = $time;
        $statement->execute($parameters);

        return $statement->rowCount() === 1;
    }

    /**
     * @param array{id:int,public_id:string,workspace_id:int,status:string,version:int,round_id?:int,judge_account_id?:int} $aggregate
     * @param array<string, scalar|null> $safeMetadata
     */
    public function appendEvent(string $aggregateKind, array $aggregate, string $operationCode, int $actorAccountId, array $safeMetadata, DateTimeImmutable $now): void
    {
        [$table, $foreignKey] = match ($aggregateKind) {
            'ROUND' => ['competition_round_events', 'round_id'],
            'ASSIGNMENT' => ['competition_judge_assignment_events', 'assignment_id'],
            'SCORE_SHEET' => ['competition_score_sheet_events', 'score_sheet_id'],
            'RESULT_RUN' => ['competition_result_events', 'result_run_id'],
            'APPEAL' => ['competition_appeal_events', 'appeal_id'],
            default => throw new \InvalidArgumentException('P6 aggregate has no event ledger.'),
        };
        $statement = $this->connections->connection()->prepare("INSERT INTO {$table} (public_id, workspace_id, {$foreignKey}, event_code, actor_account_id, safe_metadata, occurred_at) VALUES (:public_id,:workspace_id,:aggregate_id,:event_code,:actor_account_id,:metadata,:occurred_at)");
        $statement->execute([':public_id' => UuidV7::generate()->toBinary(), ':workspace_id' => $aggregate['workspace_id'], ':aggregate_id' => $aggregate['id'], ':event_code' => $operationCode, ':actor_account_id' => $actorAccountId, ':metadata' => self::json($safeMetadata), ':occurred_at' => self::time($now)]);
    }

    public function record(UuidV7 $submissionId, string $fingerprint, string $operationCode, array $aggregate, string $status, int $versionAfter, DateTimeImmutable $now): void
    {
        $statement = $this->connections->connection()->prepare('INSERT INTO competition_p6_operations (public_id, submission_id, workspace_id, operation_code, request_fingerprint, aggregate_kind, aggregate_public_id, result_status, version_after, occurred_at) VALUES (:public_id,:submission_id,:workspace_id,:operation_code,:fingerprint,:aggregate_kind,:aggregate_public_id,:result_status,:version_after,:occurred_at)');
        $statement->execute([':public_id' => UuidV7::generate()->toBinary(), ':submission_id' => $submissionId->toBinary(), ':workspace_id' => $aggregate['workspace_id'], ':operation_code' => $operationCode, ':fingerprint' => $fingerprint, ':aggregate_kind' => self::kindForOperation($operationCode), ':aggregate_public_id' => UuidV7::fromString($aggregate['public_id'])->toBinary(), ':result_status' => $status, ':version_after' => $versionAfter, ':occurred_at' => self::time($now)]);
    }

    /**
     * @param array{id:int,public_id:string,workspace_id:int,status:string,version:int,round_id?:int,judge_account_id?:int} $aggregate
     * @param array<string, scalar|null> $safePayload
     */
    public function notificationIntent(array $aggregate, ?int $accountId, string $intentType, array $safePayload, DateTimeImmutable $now): void
    {
        $key = hash('sha256', (string) $aggregate['workspace_id'] . "\0" . $aggregate['public_id'] . "\0" . $intentType . "\0" . (string) ($accountId ?? 0), true);
        $statement = $this->connections->connection()->prepare('INSERT INTO competition_notification_intents (public_id, workspace_id, account_id, intent_type, aggregate_kind, aggregate_public_id, deduplication_key, safe_payload, status, available_at, created_at) VALUES (:public_id,:workspace_id,:account_id,:intent_type,:aggregate_kind,:aggregate_public_id,:deduplication_key,:safe_payload,\'PENDING\',:now,:now) ON DUPLICATE KEY UPDATE id = id');
        $statement->execute([':public_id' => UuidV7::generate()->toBinary(), ':workspace_id' => $aggregate['workspace_id'], ':account_id' => $accountId, ':intent_type' => $intentType, ':aggregate_kind' => self::kindForOperation($intentType), ':aggregate_public_id' => UuidV7::fromString($aggregate['public_id'])->toBinary(), ':deduplication_key' => $key, ':safe_payload' => self::json($safePayload), ':now' => self::time($now)]);
    }

    public function publicResults(string $editionSlug, ?string $categorySlug, ?string $roundCode, int $limit): array
    {
        if ($limit < 1 || $limit > 200) {
            throw new \InvalidArgumentException('Public result limit is invalid.');
        }
        $sql = "SELECT edition.slug AS edition_slug, category.slug AS category_slug, round_record.round_code, row_record.public_id, row_record.rank_position, row_record.total_units, row_record.public_label, publication.public_from AS published_at FROM competition_result_runs result_run INNER JOIN competition_result_publications publication ON publication.workspace_id=result_run.workspace_id AND publication.result_run_id=result_run.id AND publication.status IN ('PROVISIONAL_PUBLISHED','FINALIZED') AND publication.public_visibility='PUBLIC' AND publication.current_public_marker=1 AND publication.package_id IS NOT NULL AND publication.projection_sha256 IS NOT NULL AND (publication.embargo_until IS NULL OR publication.embargo_until<=UTC_TIMESTAMP(6)) AND (publication.public_until IS NULL OR publication.public_until>UTC_TIMESTAMP(6)) INNER JOIN competition_result_publication_projection_heads projection_head ON projection_head.workspace_id=publication.workspace_id AND projection_head.publication_id=publication.id AND projection_head.current_projection_sha256=publication.projection_sha256 INNER JOIN competition_result_publication_projections projection ON projection.workspace_id=projection_head.workspace_id AND projection.id=projection_head.current_projection_id AND projection.publication_id=publication.id AND projection.projection_sha256=publication.projection_sha256 INNER JOIN competition_result_rows row_record ON row_record.workspace_id = result_run.workspace_id AND row_record.result_run_id = result_run.id INNER JOIN competition_rounds round_record ON round_record.workspace_id = result_run.workspace_id AND round_record.id = result_run.round_id INNER JOIN competition_categories category ON category.workspace_id = round_record.workspace_id AND category.id = round_record.category_id INNER JOIN competition_editions edition ON edition.workspace_id = category.workspace_id AND edition.id = category.edition_id WHERE result_run.status = 'PUBLISHED' AND edition.slug = :edition_slug AND edition.public_visibility = 'PUBLIC'";
        $parameters = [':edition_slug' => $editionSlug];
        if ($categorySlug !== null) {
            $sql .= ' AND category.slug = :category_slug';
            $parameters[':category_slug'] = $categorySlug;
        }
        if ($roundCode !== null) {
            $sql .= ' AND round_record.round_code = :round_code';
            $parameters[':round_code'] = $roundCode;
        }
        $sql .= ' ORDER BY edition.slug ASC, category.slug ASC, round_record.round_order ASC, row_record.rank_position ASC, row_record.id ASC LIMIT :limit';
        $statement = $this->connections->connection()->prepare($sql);
        foreach ($parameters as $name => $value) {
            $statement->bindValue($name, $value, PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $rawRow) {
            $row = self::row($rawRow);
            if ($row === null) {
                continue;
            }
            $result[] = [
                'edition_slug' => PdoResultReader::string($row, 'edition_slug'),
                'category_slug' => PdoResultReader::string($row, 'category_slug'),
                'round_code' => PdoResultReader::string($row, 'round_code'),
                'public_id' => UuidV7::fromBinary(PdoResultReader::string($row, 'public_id'))->toString(),
                'rank_position' => PdoResultReader::integer($row, 'rank_position'),
                'total_units' => PdoResultReader::integer($row, 'total_units'),
                'public_label' => PdoResultReader::string($row, 'public_label'),
                'published_at' => PdoResultReader::string($row, 'published_at'),
            ];
        }

        return $result;
    }

    private static function kindForOperation(string $operation): string
    {
        return match (true) {
            str_contains($operation, 'ASSIGNMENT') => 'ASSIGNMENT',
            str_contains($operation, 'SCORE') => 'SCORE_SHEET',
            str_contains($operation, 'RESULT') || str_contains($operation, 'DISQUAL') => 'RESULT_RUN',
            str_contains($operation, 'APPEAL') => 'APPEAL',
            default => 'ROUND',
        };
    }

    /** @param array<string, mixed> $value */
    private static function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private static function time(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }

    /** @return array<string, mixed>|null */
    private static function row(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }
        $row = [];
        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                throw new \RuntimeException('Competition database row has an invalid column name.');
            }
            $row[$key] = $item;
        }

        return $row;
    }
}
