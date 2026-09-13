<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionLive\Domain;

/** The lifecycle is intentionally independent of public projection state. */
final readonly class LiveSessionLifecycle
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'PLANNED' => ['OPEN', 'CANCELLED'],
        'OPEN' => ['PAUSED', 'RECOVERING', 'CLOSED', 'CANCELLED'],
        'PAUSED' => ['OPEN', 'RECOVERING', 'CLOSED', 'CANCELLED'],
        'RECOVERING' => ['OPEN', 'CLOSED', 'CANCELLED'],
        'CLOSED' => [],
        'CANCELLED' => [],
    ];

    public function assertTransition(string $from, string $to): void
    {
        if (!in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw new \DomainException("Live session transition {$from} to {$to} is not allowed.");
        }
    }
}
