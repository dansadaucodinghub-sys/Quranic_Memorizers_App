<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Scheduler\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Qmdb\Shared\Background\Scheduler\ScheduledExecutionSlot;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskClaimDisposition;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskRunClaim;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskRunRecord;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskRunRepository;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskRunStatus;
use Qmdb\Shared\Background\Scheduler\SchedulerExecutionId;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use RuntimeException;

final readonly class MySqlScheduledTaskRunRepository implements ScheduledTaskRunRepository
{
    public function __construct(
        private DatabaseConnectionProvider $provider,
        private TransactionManager $transactions,
    ) {
    }

    public function claim(
        ScheduledExecutionSlot $slot,
        SchedulerExecutionId $executionId,
        DateTimeImmutable $now,
        int $leaseSeconds,
    ): ScheduledTaskRunClaim {
        return $this->transactions->transactional(function () use ($slot, $executionId, $now, $leaseSeconds) {
            $leaseExpiresAt = $now->modify('+' . $leaseSeconds . ' seconds');
            $insert = $this->connection()->prepare(
                'INSERT IGNORE INTO qmdb_scheduled_task_runs '
                . '(task_id, scheduled_for, execution_id, status, attempt, claimed_at, lease_expires_at, '
                . 'version, created_at, updated_at) VALUES '
                . '(:task_id, :scheduled_for, :execution_id, :status, 1, :claimed_at, '
                . ':lease_expires_at, 1, :created_at, :updated_at)',
            );
            $insert->execute([
                ':task_id' => $slot->taskId()->value(),
                ':scheduled_for' => self::date($slot->scheduledFor()),
                ':execution_id' => $executionId->value(),
                ':status' => ScheduledTaskRunStatus::CLAIMED->value,
                ':claimed_at' => self::date($now),
                ':lease_expires_at' => self::date($leaseExpiresAt),
                ':created_at' => self::date($now),
                ':updated_at' => self::date($now),
            ]);
            if ($insert->rowCount() === 1) {
                return new ScheduledTaskRunClaim(
                    $slot,
                    ScheduledTaskClaimDisposition::CLAIMED,
                    $executionId,
                    1,
                    1,
                    $leaseExpiresAt,
                );
            }

            $record = $this->selectForUpdate($slot);
            if ($record === null) {
                throw new RuntimeException('Scheduler claim row disappeared.');
            }
            if ($record->status === ScheduledTaskRunStatus::SUCCEEDED) {
                return $this->skipped($slot, ScheduledTaskClaimDisposition::SKIPPED_SUCCEEDED);
            }
            if ($record->status === ScheduledTaskRunStatus::FAILED) {
                return $this->skipped($slot, ScheduledTaskClaimDisposition::SKIPPED_FAILED);
            }
            if ($record->leaseExpiresAt > $now) {
                return $this->skipped($slot, ScheduledTaskClaimDisposition::SKIPPED_ACTIVE);
            }

            $attempt = $record->attempt + 1;
            $version = $record->version + 1;
            $update = $this->connection()->prepare(
                'UPDATE qmdb_scheduled_task_runs SET execution_id = :execution_id, status = :status, '
                . 'attempt = :attempt, claimed_at = :claimed_at, lease_expires_at = :lease_expires_at, '
                . 'started_at = NULL, completed_at = NULL, failed_at = NULL, duration_ms = NULL, '
                . 'failure_code = NULL, version = :next_version, updated_at = :updated_at '
                . 'WHERE task_id = :task_id AND scheduled_for = :scheduled_for AND version = :version',
            );
            $update->execute([
                ':execution_id' => $executionId->value(),
                ':status' => ScheduledTaskRunStatus::CLAIMED->value,
                ':attempt' => $attempt,
                ':claimed_at' => self::date($now),
                ':lease_expires_at' => self::date($leaseExpiresAt),
                ':next_version' => $version,
                ':updated_at' => self::date($now),
                ':task_id' => $slot->taskId()->value(),
                ':scheduled_for' => self::date($slot->scheduledFor()),
                ':version' => $record->version,
            ]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('Scheduler reclaim lost optimistic ownership.');
            }

            return new ScheduledTaskRunClaim(
                $slot,
                ScheduledTaskClaimDisposition::RECLAIMED,
                $executionId,
                $attempt,
                $version,
                $leaseExpiresAt,
            );
        });
    }

    public function markRunning(
        ScheduledTaskRunClaim $claim,
        DateTimeImmutable $startedAt,
    ): ScheduledTaskRunClaim {
        $version = $claim->version() + 1;
        $this->ownedUpdate(
            $claim,
            'status = :status, started_at = :started_at, version = :next_version, updated_at = :updated_at',
            [
                ':status' => ScheduledTaskRunStatus::RUNNING->value,
                ':started_at' => self::date($startedAt),
                ':updated_at' => self::date($startedAt),
                ':next_version' => $version,
            ],
        );

        return $claim->running($version);
    }

    public function markSucceeded(
        ScheduledTaskRunClaim $claim,
        DateTimeImmutable $completedAt,
        int $durationMilliseconds,
    ): void {
        $this->ownedUpdate(
            $claim,
            'status = :status, completed_at = :completed_at, duration_ms = :duration_ms, '
            . 'failure_code = NULL, version = :next_version, updated_at = :updated_at',
            [
                ':status' => ScheduledTaskRunStatus::SUCCEEDED->value,
                ':completed_at' => self::date($completedAt),
                ':updated_at' => self::date($completedAt),
                ':duration_ms' => $durationMilliseconds,
                ':next_version' => $claim->version() + 1,
            ],
        );
    }

    public function markFailed(
        ScheduledTaskRunClaim $claim,
        DateTimeImmutable $failedAt,
        int $durationMilliseconds,
        string $safeFailureCode,
    ): void {
        if (preg_match('/\A[A-Z][A-Z0-9_]{1,63}\z/D', $safeFailureCode) !== 1) {
            throw new RuntimeException('Scheduler failure code is invalid.');
        }
        $this->ownedUpdate(
            $claim,
            'status = :status, failed_at = :failed_at, duration_ms = :duration_ms, failure_code = :failure_code, '
            . 'version = :next_version, updated_at = :updated_at',
            [
                ':status' => ScheduledTaskRunStatus::FAILED->value,
                ':failed_at' => self::date($failedAt),
                ':updated_at' => self::date($failedAt),
                ':duration_ms' => $durationMilliseconds,
                ':failure_code' => $safeFailureCode,
                ':next_version' => $claim->version() + 1,
            ],
        );
    }

    public function find(ScheduledExecutionSlot $slot): ?ScheduledTaskRunRecord
    {
        $statement = $this->connection()->prepare(
            'SELECT execution_id, status, attempt, lease_expires_at, version, failure_code '
            . 'FROM qmdb_scheduled_task_runs WHERE task_id = :task_id AND scheduled_for = :scheduled_for',
        );
        $statement->execute([
            ':task_id' => $slot->taskId()->value(),
            ':scheduled_for' => self::date($slot->scheduledFor()),
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->record($slot, $row) : null;
    }

    private function selectForUpdate(ScheduledExecutionSlot $slot): ?ScheduledTaskRunRecord
    {
        $statement = $this->connection()->prepare(
            'SELECT execution_id, status, attempt, lease_expires_at, version, failure_code '
            . 'FROM qmdb_scheduled_task_runs WHERE task_id = :task_id AND scheduled_for = :scheduled_for FOR UPDATE',
        );
        $statement->execute([
            ':task_id' => $slot->taskId()->value(),
            ':scheduled_for' => self::date($slot->scheduledFor()),
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->record($slot, $row) : null;
    }

    /** @param array<mixed, mixed> $row */
    private function record(ScheduledExecutionSlot $slot, array $row): ScheduledTaskRunRecord
    {
        $failureCode = $row['failure_code'] ?? null;
        if ($failureCode !== null && !is_string($failureCode)) {
            throw new RuntimeException('Scheduler failure code column is invalid.');
        }

        return new ScheduledTaskRunRecord(
            $slot,
            new SchedulerExecutionId($this->stringColumn($row, 'execution_id')),
            ScheduledTaskRunStatus::from($this->stringColumn($row, 'status')),
            $this->integerColumn($row, 'attempt'),
            self::parseDate($this->stringColumn($row, 'lease_expires_at')),
            $this->integerColumn($row, 'version'),
            $failureCode,
        );
    }

    /** @param array<mixed, mixed> $row */
    private function stringColumn(array $row, string $column): string
    {
        $value = $row[$column] ?? null;
        if (!is_string($value)) {
            throw new RuntimeException('Scheduler ledger string column is invalid.');
        }

        return $value;
    }

    /** @param array<mixed, mixed> $row */
    private function integerColumn(array $row, string $column): int
    {
        $value = $row[$column] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value) || preg_match('/\A[0-9]+\z/D', $value) !== 1) {
            throw new RuntimeException('Scheduler ledger integer column is invalid.');
        }

        return (int) $value;
    }

    /** @param array<string, int|string> $parameters */
    private function ownedUpdate(ScheduledTaskRunClaim $claim, string $setClause, array $parameters): void
    {
        $this->transactions->transactional(function () use ($claim, $setClause, $parameters): void {
            $statement = $this->connection()->prepare(
                'UPDATE qmdb_scheduled_task_runs SET ' . $setClause
                . ' WHERE task_id = :task_id AND scheduled_for = :scheduled_for '
                . 'AND execution_id = :execution_id AND version = :version',
            );
            $statement->execute([
                ...$parameters,
                ':task_id' => $claim->slot()->taskId()->value(),
                ':scheduled_for' => self::date($claim->slot()->scheduledFor()),
                ':execution_id' => $claim->executionId()->value(),
                ':version' => $claim->version(),
            ]);
            if ($statement->rowCount() !== 1) {
                throw new RuntimeException('Stale scheduler owner cannot update the run.');
            }
        });
    }

    private function skipped(
        ScheduledExecutionSlot $slot,
        ScheduledTaskClaimDisposition $disposition,
    ): ScheduledTaskRunClaim {
        return new ScheduledTaskRunClaim($slot, $disposition, null, 0, 0, null);
    }

    private function connection(): PDO
    {
        return $this->provider->connection();
    }

    private static function date(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }

    private static function parseDate(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}
