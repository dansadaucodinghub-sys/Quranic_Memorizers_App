<?php

declare(strict_types=1);

namespace Qmdb\Modules\PilotOfflineRollout\Domain;

final readonly class PilotRolloutLifecycle
{
    /** @var array<string, list<string>> */
    private const array PILOT = [
        'DRAFT' => ['READINESS_REVIEW', 'CANCELLED'],
        'READINESS_REVIEW' => ['APPROVED', 'DRAFT', 'CANCELLED'],
        'APPROVED' => ['ACTIVE', 'CANCELLED'],
        'ACTIVE' => ['PAUSED', 'COMPLETED', 'FAILED'],
        'PAUSED' => ['ACTIVE', 'CANCELLED', 'FAILED'],
    ];

    /** @var array<string, list<string>> */
    private const array WAVE = [
        'PLANNED' => ['READINESS_REVIEW', 'CANCELLED'],
        'READINESS_REVIEW' => ['APPROVED', 'PLANNED', 'CANCELLED'],
        'APPROVED' => ['ACTIVE', 'CANCELLED'],
        'ACTIVE' => ['PAUSED', 'CONTAINED', 'COMPLETED', 'FAILED'],
        'PAUSED' => ['ACTIVE', 'CONTAINED', 'CANCELLED', 'FAILED'],
        'CONTAINED' => ['PAUSED', 'CANCELLED', 'FAILED'],
    ];

    public function assertPilotTransition(string $from, string $to): void
    {
        $this->assertTransition(self::PILOT, $from, $to);
    }

    public function assertWaveTransition(string $from, string $to): void
    {
        $this->assertTransition(self::WAVE, $from, $to);
    }

    /** @param array<string, list<string>> $graph */
    private function assertTransition(array $graph, string $from, string $to): void
    {
        if (!in_array($to, $graph[$from] ?? [], true)) {
            throw new \DomainException('Governed lifecycle transition is not permitted.');
        }
    }
}
