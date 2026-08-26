<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Scheduler;

use DateTimeImmutable;
use LogicException;

final readonly class ScheduledTaskRunClaim
{
    public function __construct(
        private ScheduledExecutionSlot $slot,
        private ScheduledTaskClaimDisposition $disposition,
        private ?SchedulerExecutionId $executionId,
        private int $attempt,
        private int $version,
        private ?DateTimeImmutable $leaseExpiresAt,
    ) {
    }

    public function slot(): ScheduledExecutionSlot
    {
        return $this->slot;
    }

    public function disposition(): ScheduledTaskClaimDisposition
    {
        return $this->disposition;
    }

    public function isAcquired(): bool
    {
        return in_array($this->disposition, [
            ScheduledTaskClaimDisposition::CLAIMED,
            ScheduledTaskClaimDisposition::RECLAIMED,
        ], true);
    }

    public function executionId(): SchedulerExecutionId
    {
        if ($this->executionId === null) {
            throw new LogicException('Skipped scheduler claim has no execution ID.');
        }

        return $this->executionId;
    }

    public function attempt(): int
    {
        return $this->attempt;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function leaseExpiresAt(): DateTimeImmutable
    {
        if ($this->leaseExpiresAt === null) {
            throw new LogicException('Skipped scheduler claim has no lease expiry.');
        }

        return $this->leaseExpiresAt;
    }

    public function running(int $newVersion): self
    {
        return new self(
            $this->slot,
            $this->disposition,
            $this->executionId,
            $this->attempt,
            $newVersion,
            $this->leaseExpiresAt,
        );
    }
}
