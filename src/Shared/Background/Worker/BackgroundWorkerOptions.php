<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Worker;

use InvalidArgumentException;
use Qmdb\Shared\Background\Configuration\BackgroundExecutionConfiguration;

final readonly class BackgroundWorkerOptions
{
    public function __construct(
        private bool $runOnce,
        private int $maximumJobs,
        private int $maximumRuntimeSeconds,
        private int $idleSleepMilliseconds,
        private int $maximumMemoryMegabytes,
    ) {
        if ($maximumJobs < 1 || $maximumJobs > 10_000) {
            throw new InvalidArgumentException('Worker maximum jobs is invalid.');
        }
        if ($maximumRuntimeSeconds < 1 || $maximumRuntimeSeconds > 86_400) {
            throw new InvalidArgumentException('Worker maximum runtime is invalid.');
        }
        if ($idleSleepMilliseconds < 0 || $idleSleepMilliseconds > 60_000) {
            throw new InvalidArgumentException('Worker idle sleep is invalid.');
        }
        if ($maximumMemoryMegabytes < 32 || $maximumMemoryMegabytes > 65_536) {
            throw new InvalidArgumentException('Worker maximum memory is invalid.');
        }
    }

    public static function fromConfiguration(
        BackgroundExecutionConfiguration $configuration,
        bool $runOnce = false,
    ): self {
        return new self(
            $runOnce,
            $configuration->maximumJobs(),
            $configuration->maximumRuntimeSeconds(),
            $configuration->idleSleepMilliseconds(),
            $configuration->maximumMemoryMegabytes(),
        );
    }

    public function runOnce(): bool
    {
        return $this->runOnce;
    }

    public function maximumJobs(): int
    {
        return $this->runOnce ? 1 : $this->maximumJobs;
    }

    public function maximumRuntimeSeconds(): int
    {
        return $this->maximumRuntimeSeconds;
    }

    public function idleSleepMilliseconds(): int
    {
        return $this->idleSleepMilliseconds;
    }

    public function maximumMemoryBytes(): int
    {
        return $this->maximumMemoryMegabytes * 1_048_576;
    }
}
