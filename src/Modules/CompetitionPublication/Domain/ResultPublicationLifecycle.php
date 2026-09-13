<?php

declare(strict_types=1);

namespace Qmdb\Modules\CompetitionPublication\Domain;

/** Closed P7 publication lifecycle; packages and result runs remain immutable. */
final readonly class ResultPublicationLifecycle
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'PREPARED' => ['PROVISIONAL_PUBLISHED', 'FINALIZED', 'WITHDRAWN'],
        'PROVISIONAL_PUBLISHED' => ['HELD', 'FINALIZED', 'WITHDRAWN', 'SUPERSEDED'],
        'HELD' => ['PROVISIONAL_PUBLISHED', 'WITHDRAWN', 'SUPERSEDED'],
        'FINALIZED' => ['WITHDRAWN', 'SUPERSEDED'],
        'WITHDRAWN' => ['ARCHIVED'],
        'SUPERSEDED' => ['ARCHIVED'],
        'ARCHIVED' => [],
    ];

    public function assertTransition(string $from, string $to, bool $directFinalizationAllowed = false): void
    {
        if ($from === 'PREPARED' && $to === 'FINALIZED' && !$directFinalizationAllowed) {
            throw new \DomainException('Direct finalization is not permitted by publication policy.');
        }
        if (!in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw new \DomainException("Result publication transition {$from} to {$to} is not allowed.");
        }
    }
}
