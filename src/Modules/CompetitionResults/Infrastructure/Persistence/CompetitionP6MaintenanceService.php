<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionResults\Infrastructure\Persistence;

use DateTimeImmutable;
use PDO;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Time\Clock;
use Qmdb\Shared\Identifier\UuidV7;

/** Bounded, tenant-preserving P6 background maintenance. */
final readonly class CompetitionP6MaintenanceService
{
    private const ROUND_BATCH = 50;
    private const REMINDER_BATCH = 100;
    private const RECONCILIATION_BATCH = 100;

    public function __construct(private DatabaseConnectionProvider $connections, private Clock $clock)
    {
    }

    /** @return array{opened:int,closed:int,blocked:int,dry_run:bool} */
    public function processRounds(bool $dryRun): array
    {
        $pdo = $this->connections->connection();
        $now = $this->now();
        $opened = $this->dueRounds($pdo, 'READY', 'opens_at', $now, self::ROUND_BATCH);
        $closed = $this->dueRounds($pdo, 'SCORING_OPEN', 'closes_at', $now, self::ROUND_BATCH);
        $result = ['opened' => 0, 'closed' => 0, 'blocked' => 0, 'dry_run' => $dryRun];
        foreach ($opened as $round) {
            if (!$dryRun && $this->transitionRound($pdo, $round, 'SCORING_OPEN', 'SCORING_OPENED', $now)) {
                ++$result['opened'];
            }
        }
        foreach ($closed as $round) {
            if ($this->hasIncompleteSheets($pdo, $round['workspace_id'], $round['id'])) {
                ++$result['blocked'];
                continue;
            }
            if (!$dryRun && $this->transitionRound($pdo, $round, 'SCORING_CLOSED', 'SCORING_CLOSED', $now)) {
                ++$result['closed'];
            }
        }

        return $result;
    }

    /** @return array{candidates:int,created:int,dry_run:bool} */
    public function remindScoreSheets(bool $dryRun): array
    {
        $pdo = $this->connections->connection();
        $now = $this->now();
        $statement = $pdo->prepare("SELECT assignment.workspace_id, assignment.id AS assignment_id, assignment.public_id AS assignment_public_id, assignment.judge_id, judge.account_id FROM competition_judge_assignments assignment INNER JOIN competition_judge_panels panel ON panel.workspace_id=assignment.workspace_id AND panel.id=assignment.panel_id INNER JOIN competition_rounds round_record ON round_record.workspace_id=panel.workspace_id AND round_record.id=panel.round_id INNER JOIN competition_judges judge ON judge.workspace_id=assignment.workspace_id AND judge.id=assignment.judge_id WHERE assignment.status='ACCEPTED' AND judge.status='ACTIVE' AND round_record.status='SCORING_OPEN' AND round_record.closes_at IS NOT NULL AND round_record.closes_at >= :start_at AND round_record.closes_at < DATE_ADD(:end_at, INTERVAL 60 MINUTE) ORDER BY assignment.workspace_id, assignment.id LIMIT " . self::REMINDER_BATCH);
        if ($statement === false) {
            throw new \RuntimeException('P6 reminder selection could not be prepared.');
        }
        $statement->execute([':start_at' => $now, ':end_at' => $now]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $created = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (!$dryRun && $this->insertNotification($pdo, $this->int($row, 'workspace_id'), $this->int($row, 'account_id'), 'SCORE_SHEET_REMINDER', 'JUDGE_ASSIGNMENT', $this->string($row, 'assignment_public_id'), $now)) {
                ++$created;
            }
        }

        return ['candidates' => count($rows), 'created' => $created, 'dry_run' => $dryRun];
    }

    /** @return array{opened:int,closed:int,dry_run:bool} */
    public function processAppealWindows(bool $dryRun): array
    {
        $pdo = $this->connections->connection();
        $now = $this->now();
        $opened = $this->dueAppealWindows($pdo, 'SCHEDULED', 'opens_at', $now, 50);
        $closed = $this->dueAppealWindows($pdo, 'OPEN', 'closes_at', $now, 50);
        $result = ['opened' => 0, 'closed' => 0, 'dry_run' => $dryRun];
        foreach ($opened as $window) {
            if (!$dryRun && $this->transitionAppealWindow($pdo, $window, 'OPEN', $now)) {
                ++$result['opened'];
            }
        }
        foreach ($closed as $window) {
            if (!$dryRun && $this->transitionAppealWindow($pdo, $window, 'CLOSED', $now)) {
                ++$result['closed'];
            }
        }

        return $result;
    }

    /** @return array{checked:int,drift:int} */
    public function reconcileScoreSheets(): array
    {
        $statement = $this->connections->connection()->prepare("SELECT sheet.id, sheet.total_units, sheet.penalty_units, COALESCE(SUM(entry.weighted_units),0) AS weighted_units FROM competition_score_sheets sheet LEFT JOIN competition_score_entries entry ON entry.workspace_id=sheet.workspace_id AND entry.score_sheet_id=sheet.id WHERE sheet.status IN ('SUBMITTED','LOCKED') GROUP BY sheet.id, sheet.total_units, sheet.penalty_units ORDER BY sheet.id LIMIT " . self::RECONCILIATION_BATCH);
        if ($statement === false) {
            throw new \RuntimeException('P6 score reconciliation could not be prepared.');
        }
        $statement->execute();
        $drift = 0;
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            if (is_array($row) && $this->int($row, 'total_units') - $this->int($row, 'penalty_units') !== $this->int($row, 'weighted_units')) {
                ++$drift;
            }
        }

        return ['checked' => count($rows), 'drift' => $drift];
    }

    /** @return array{checked:int,drift:int} */
    public function reconcileResults(): array
    {
        $statement = $this->connections->connection()->prepare("SELECT result_run.id, result_run.result_checksum_sha256, COUNT(result_row.id) AS row_count FROM competition_result_runs result_run LEFT JOIN competition_result_rows result_row ON result_row.workspace_id=result_run.workspace_id AND result_row.result_run_id=result_run.id GROUP BY result_run.id, result_run.result_checksum_sha256 ORDER BY result_run.id LIMIT " . self::RECONCILIATION_BATCH);
        if ($statement === false) {
            throw new \RuntimeException('P6 result reconciliation could not be prepared.');
        }
        $statement->execute();
        $drift = 0;
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            if (is_array($row) && (!is_string($row['result_checksum_sha256'] ?? null) || $this->int($row, 'row_count') < 1)) {
                ++$drift;
            }
        }

        return ['checked' => count($rows), 'drift' => $drift];
    }

    /** @return list<array{id:int,workspace_id:int,public_id:string,version:int}> */
    private function dueRounds(PDO $pdo, string $status, string $column, string $now, int $limit): array
    {
        $statement = $pdo->prepare("SELECT id, workspace_id, public_id, version FROM competition_rounds WHERE status=:status AND {$column} IS NOT NULL AND {$column}<=:now ORDER BY {$column}, id LIMIT {$limit}");
        if ($statement === false) {
            throw new \RuntimeException('P6 round selection could not be prepared.');
        }
        $statement->execute([':status' => $status, ':now' => $now]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $result[] = ['id' => $this->int($row, 'id'), 'workspace_id' => $this->int($row, 'workspace_id'), 'public_id' => $this->string($row, 'public_id'), 'version' => $this->int($row, 'version')];
            }
        }
        return $result;
    }

    /** @param array{id:int,workspace_id:int,public_id:string,version:int} $round */
    private function transitionRound(PDO $pdo, array $round, string $to, string $event, string $now): bool
    {
        $pdo->beginTransaction();
        try {
            $update = $pdo->prepare('UPDATE competition_rounds SET status=:status, version=version+1, updated_at=:now WHERE id=:id AND workspace_id=:workspace_id AND version=:version');
            if ($update === false) {
                throw new \RuntimeException('P6 round update could not be prepared.');
            }
            $update->execute([':status' => $to, ':now' => $now, ':id' => $round['id'], ':workspace_id' => $round['workspace_id'], ':version' => $round['version']]);
            if ($update->rowCount() !== 1) {
                $pdo->rollBack();
                return false;
            }
            $eventInsert = $pdo->prepare('INSERT INTO competition_round_events (public_id,workspace_id,round_id,event_code,actor_account_id,safe_metadata,occurred_at) VALUES (:public_id,:workspace_id,:round_id,:event_code,NULL,:metadata,:now)');
            if ($eventInsert === false) {
                throw new \RuntimeException('P6 round event could not be prepared.');
            }
            $eventInsert->execute([':public_id' => UuidV7::generate()->toBinary(), ':workspace_id' => $round['workspace_id'], ':round_id' => $round['id'], ':event_code' => $event, ':metadata' => '{}', ':now' => $now]);
            $pdo->commit();
            return true;
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            } throw $error;
        }
    }

    private function hasIncompleteSheets(PDO $pdo, int $workspaceId, int $roundId): bool
    {
        $statement = $pdo->prepare("SELECT COUNT(*) FROM competition_score_sheets WHERE workspace_id=:workspace_id AND round_id=:round_id AND status IN ('DRAFT','SUBMITTED')");
        if ($statement === false) {
            throw new \RuntimeException('P6 close safety query could not be prepared.');
        }
        $statement->execute([':workspace_id' => $workspaceId, ':round_id' => $roundId]);
        return (int) $statement->fetchColumn() > 0;
    }

    /** @return list<array{id:int,workspace_id:int,version:int,result_run_id:int}> */
    private function dueAppealWindows(PDO $pdo, string $status, string $column, string $now, int $limit): array
    {
        $statement = $pdo->prepare("SELECT window_record.id, window_record.workspace_id, window_record.version, window_record.result_run_id FROM competition_appeal_windows window_record INNER JOIN competition_result_runs result_run ON result_run.workspace_id=window_record.workspace_id AND result_run.id=window_record.result_run_id WHERE window_record.status=:status AND window_record.{$column}<=:now AND result_run.status='PUBLISHED' ORDER BY window_record.{$column}, window_record.id LIMIT {$limit}");
        if ($statement === false) {
            throw new \RuntimeException('P6 appeal-window selection could not be prepared.');
        }
        $statement->execute([':status' => $status, ':now' => $now]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $result[] = ['id' => $this->int($row, 'id'), 'workspace_id' => $this->int($row, 'workspace_id'), 'version' => $this->int($row, 'version'), 'result_run_id' => $this->int($row, 'result_run_id')];
            }
        }
        return $result;
    }

    /** @param array{id:int,workspace_id:int,version:int,result_run_id:int} $window */
    private function transitionAppealWindow(PDO $pdo, array $window, string $to, string $now): bool
    {
        $statement = $pdo->prepare('UPDATE competition_appeal_windows SET status=:status, version=version+1 WHERE id=:id AND workspace_id=:workspace_id AND version=:version');
        if ($statement === false) {
            throw new \RuntimeException('P6 appeal-window update could not be prepared.');
        }
        $statement->execute([':status' => $to, ':id' => $window['id'], ':workspace_id' => $window['workspace_id'], ':version' => $window['version']]);
        return $statement->rowCount() === 1;
    }

    private function insertNotification(PDO $pdo, int $workspaceId, int $accountId, string $type, string $kind, string $aggregatePublicId, string $now): bool
    {
        $statement = $pdo->prepare('INSERT IGNORE INTO competition_notification_intents (public_id,workspace_id,account_id,intent_type,aggregate_kind,aggregate_public_id,deduplication_key,safe_payload,status,attempts,available_at,created_at) VALUES (:public_id,:workspace_id,:account_id,:intent_type,:aggregate_kind,:aggregate_public_id,:deduplication_key,:payload,\'PENDING\',0,:now,:now)');
        if ($statement === false) {
            throw new \RuntimeException('P6 notification insert could not be prepared.');
        }
        $statement->execute([':public_id' => UuidV7::generate()->toBinary(), ':workspace_id' => $workspaceId, ':account_id' => $accountId, ':intent_type' => $type, ':aggregate_kind' => $kind, ':aggregate_public_id' => $aggregatePublicId, ':deduplication_key' => hash('sha256', $type . "\n" . $aggregatePublicId, true), ':payload' => '{}', ':now' => $now]);
        return $statement->rowCount() === 1;
    }

    private function now(): string
    {
        return $this->clock->now()->format('Y-m-d H:i:s.u');
    }

    /** @param array<array-key,mixed> $row */
    private function int(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^(?:0|[1-9][0-9]*)$/', $value) === 1) {
            return (int) $value;
        }
        throw new \UnexpectedValueException('P6 maintenance row integer is invalid.');
    }

    /** @param array<array-key,mixed> $row */
    private function string(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \UnexpectedValueException('P6 maintenance row string is invalid.');
        }
        return $value;
    }
}
