<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionLive\Domain;

final readonly class LiveParticipantLifecycle
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'SCHEDULED' => ['CHECKED_IN', 'ABSENT', 'WITHDRAWN', 'DISQUALIFIED', 'CANCELLED'],
        'CHECKED_IN' => ['CALLED', 'WITHDRAWN', 'DISQUALIFIED', 'CANCELLED'],
        'CALLED' => ['READY', 'ABSENT', 'DISQUALIFIED', 'CANCELLED'],
        'READY' => ['PERFORMING', 'DISQUALIFIED', 'CANCELLED'],
        'PERFORMING' => ['INTERRUPTED', 'COMPLETED', 'DISQUALIFIED', 'CANCELLED'],
        'INTERRUPTED' => ['PERFORMING', 'COMPLETED', 'DISQUALIFIED', 'CANCELLED'],
        'COMPLETED' => [],
        'ABSENT' => [],
        'WITHDRAWN' => [],
        'DISQUALIFIED' => [],
        'CANCELLED' => [],
    ];

    public function assertTransition(string $from, string $to): void
    {
        if (!in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw new \DomainException("Live participant transition {$from} to {$to} is not allowed.");
        }
    }
}
