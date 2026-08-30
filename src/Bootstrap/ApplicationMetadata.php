<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap;

final readonly class ApplicationMetadata
{
    private function __construct(
        private string $applicationName,
        private string $applicationCode,
        private string $frozenBaseline,
        private string $currentPhase,
        private string $currentBatch,
        private string $developmentVersion,
    ) {
    }

    public static function current(): self
    {
        return new self(
            applicationName: 'Qur’an Memorizer DB',
            applicationCode: 'QMDB',
            frozenBaseline: 'QMDB-P0-FRZ-001',
            currentPhase: 'P2',
            currentBatch: 'QMDB-P2-B09',
            developmentVersion: '0.1.0-dev',
        );
    }

    public function applicationName(): string
    {
        return $this->applicationName;
    }

    public function applicationCode(): string
    {
        return $this->applicationCode;
    }

    public function frozenBaseline(): string
    {
        return $this->frozenBaseline;
    }

    public function currentPhase(): string
    {
        return $this->currentPhase;
    }

    public function currentBatch(): string
    {
        return $this->currentBatch;
    }

    public function developmentVersion(): string
    {
        return $this->developmentVersion;
    }

    /**
     * @return array{
     *     application_name: string,
     *     application_code: string,
     *     frozen_baseline: string,
     *     current_phase: string,
     *     current_batch: string,
     *     development_version: string
     * }
     */
    public function toArray(): array
    {
        return [
            'application_name' => $this->applicationName,
            'application_code' => $this->applicationCode,
            'frozen_baseline' => $this->frozenBaseline,
            'current_phase' => $this->currentPhase,
            'current_batch' => $this->currentBatch,
            'development_version' => $this->developmentVersion,
        ];
    }

    /** @return list<string> */
    public function toCliLines(): array
    {
        return [
            'Application Name: ' . $this->applicationName,
            'Application Code: ' . $this->applicationCode,
            'Frozen Baseline: ' . $this->frozenBaseline,
            'Current Phase: ' . $this->currentPhase,
            'Current Batch: ' . $this->currentBatch,
            'Development Version: ' . $this->developmentVersion,
        ];
    }
}
