<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Background;

use DateTimeImmutable;
use Qmdb\Shared\Background\Scheduler\ScheduledExecutionSlot;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskClaimDisposition;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskRunClaim;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskRunRecord;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskRunRepository;
use Qmdb\Shared\Background\Scheduler\ScheduledTaskRunStatus;
use Qmdb\Shared\Background\Scheduler\SchedulerExecutionId;
use RuntimeException;

final class InMemoryScheduledTaskRunRepository implements ScheduledTaskRunRepository
{
    /** @var array<string, ScheduledTaskRunRecord> */
    private array $records = [];
    /** @var list<string> */
    public array $actions = [];

    public function claim(
        ScheduledExecutionSlot $slot,
        SchedulerExecutionId $executionId,
        DateTimeImmutable $now,
        int $leaseSeconds,
    ): ScheduledTaskRunClaim {
        $key = $this->key($slot);
        $record = $this->records[$key] ?? null;
        if ($record === null) {
            $claim = new ScheduledTaskRunClaim(
                $slot,
                ScheduledTaskClaimDisposition::CLAIMED,
                $executionId,
                1,
                1,
                $now->modify('+' . $leaseSeconds . ' seconds'),
            );
            $this->records[$key] = $this->record($claim, ScheduledTaskRunStatus::CLAIMED);
            $this->actions[] = 'claimed';

            return $claim;
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
        $claim = new ScheduledTaskRunClaim(
            $slot,
            ScheduledTaskClaimDisposition::RECLAIMED,
            $executionId,
            $record->attempt + 1,
            $record->version + 1,
            $now->modify('+' . $leaseSeconds . ' seconds'),
        );
        $this->records[$key] = $this->record($claim, ScheduledTaskRunStatus::CLAIMED);
        $this->actions[] = 'reclaimed';

        return $claim;
    }

    public function markRunning(
        ScheduledTaskRunClaim $claim,
        DateTimeImmutable $startedAt,
    ): ScheduledTaskRunClaim {
        $this->assertOwner($claim);
        $running = $claim->running($claim->version() + 1);
        $this->records[$this->key($claim->slot())] = $this->record($running, ScheduledTaskRunStatus::RUNNING);
        $this->actions[] = 'running';

        return $running;
    }

    public function markSucceeded(
        ScheduledTaskRunClaim $claim,
        DateTimeImmutable $completedAt,
        int $durationMilliseconds,
    ): void {
        $this->assertOwner($claim);
        $this->records[$this->key($claim->slot())] = new ScheduledTaskRunRecord(
            $claim->slot(),
            $claim->executionId(),
            ScheduledTaskRunStatus::SUCCEEDED,
            $claim->attempt(),
            $claim->leaseExpiresAt(),
            $claim->version() + 1,
            null,
        );
        $this->actions[] = 'succeeded';
    }

    public function markFailed(
        ScheduledTaskRunClaim $claim,
        DateTimeImmutable $failedAt,
        int $durationMilliseconds,
        string $safeFailureCode,
    ): void {
        $this->assertOwner($claim);
        $this->records[$this->key($claim->slot())] = new ScheduledTaskRunRecord(
            $claim->slot(),
            $claim->executionId(),
            ScheduledTaskRunStatus::FAILED,
            $claim->attempt(),
            $claim->leaseExpiresAt(),
            $claim->version() + 1,
            $safeFailureCode,
        );
        $this->actions[] = 'failed:' . $safeFailureCode;
    }

    public function find(ScheduledExecutionSlot $slot): ?ScheduledTaskRunRecord
    {
        return $this->records[$this->key($slot)] ?? null;
    }

    public function seed(ScheduledTaskRunRecord $record): void
    {
        $this->records[$this->key($record->slot)] = $record;
    }

    private function assertOwner(ScheduledTaskRunClaim $claim): void
    {
        $record = $this->find($claim->slot());
        if (
            $record === null
            || $record->executionId->value() !== $claim->executionId()->value()
            || $record->version !== $claim->version()
        ) {
            throw new RuntimeException('Stale scheduler owner cannot update the run.');
        }
    }

    private function record(
        ScheduledTaskRunClaim $claim,
        ScheduledTaskRunStatus $status,
    ): ScheduledTaskRunRecord {
        return new ScheduledTaskRunRecord(
            $claim->slot(),
            $claim->executionId(),
            $status,
            $claim->attempt(),
            $claim->leaseExpiresAt(),
            $claim->version(),
            null,
        );
    }

    private function skipped(
        ScheduledExecutionSlot $slot,
        ScheduledTaskClaimDisposition $disposition,
    ): ScheduledTaskRunClaim {
        return new ScheduledTaskRunClaim($slot, $disposition, null, 0, 0, null);
    }

    private function key(ScheduledExecutionSlot $slot): string
    {
        return $slot->taskId()->value() . '|' . $slot->scheduledFor()->format('Y-m-d H:i:s.u');
    }
}
