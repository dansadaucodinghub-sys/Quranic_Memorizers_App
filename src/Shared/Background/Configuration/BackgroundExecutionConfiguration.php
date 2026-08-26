<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Configuration;

use InvalidArgumentException;

final readonly class BackgroundExecutionConfiguration
{
    public function __construct(
        private int $maximumJobs,
        private int $maximumRuntimeSeconds,
        private int $idleSleepMilliseconds,
        private int $maximumMemoryMegabytes,
        private bool $requirePcntlInProduction,
        private int $schedulerLeaseSeconds,
        private int $schedulerLockTimeoutSeconds,
    ) {
        if ($maximumJobs < 1 || $maximumJobs > 10_000) {
            throw new InvalidArgumentException('Worker maximum jobs is outside the safe range.');
        }
        if ($maximumRuntimeSeconds < 1 || $maximumRuntimeSeconds > 86_400) {
            throw new InvalidArgumentException('Worker maximum runtime is outside the safe range.');
        }
        if ($idleSleepMilliseconds < 0 || $idleSleepMilliseconds > 60_000) {
            throw new InvalidArgumentException('Worker idle sleep is outside the safe range.');
        }
        if ($maximumMemoryMegabytes < 32 || $maximumMemoryMegabytes > 65_536) {
            throw new InvalidArgumentException('Worker maximum memory is outside the safe range.');
        }
        if ($schedulerLeaseSeconds < 1 || $schedulerLeaseSeconds > 86_400) {
            throw new InvalidArgumentException('Scheduler lease is outside the safe range.');
        }
        if ($schedulerLockTimeoutSeconds < 0 || $schedulerLockTimeoutSeconds > 60) {
            throw new InvalidArgumentException('Scheduler lock timeout is outside the safe range.');
        }
    }

    public function maximumJobs(): int
    {
        return $this->maximumJobs;
    }

    public function maximumRuntimeSeconds(): int
    {
        return $this->maximumRuntimeSeconds;
    }

    public function idleSleepMilliseconds(): int
    {
        return $this->idleSleepMilliseconds;
    }

    public function maximumMemoryMegabytes(): int
    {
        return $this->maximumMemoryMegabytes;
    }

    public function requirePcntlInProduction(): bool
    {
        return $this->requirePcntlInProduction;
    }

    public function schedulerLeaseSeconds(): int
    {
        return $this->schedulerLeaseSeconds;
    }

    public function schedulerLockTimeoutSeconds(): int
    {
        return $this->schedulerLockTimeoutSeconds;
    }

    /** @return array<string, bool|int> */
    public function toSafeArray(): array
    {
        return [
            'worker_max_jobs' => $this->maximumJobs,
            'worker_max_runtime_seconds' => $this->maximumRuntimeSeconds,
            'worker_idle_sleep_ms' => $this->idleSleepMilliseconds,
            'worker_max_memory_mb' => $this->maximumMemoryMegabytes,
            'worker_require_pcntl_in_production' => $this->requirePcntlInProduction,
            'scheduler_run_lease_seconds' => $this->schedulerLeaseSeconds,
            'scheduler_lock_timeout_seconds' => $this->schedulerLockTimeoutSeconds,
        ];
    }
}
