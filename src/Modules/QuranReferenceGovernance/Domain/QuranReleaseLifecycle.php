<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Domain;

use InvalidArgumentException;

/** The release state graph is independent of text import and rejects implicit transitions. */
final readonly class QuranReleaseLifecycle
{
    /** @var array<string,list<string>> */
    private const array TRANSITIONS = [
        'DRAFT' => ['STAGED', 'REJECTED'],
        'STAGED' => ['VALIDATED', 'REJECTED'],
        'VALIDATED' => ['APPROVED', 'REJECTED'],
        'APPROVED' => ['ACTIVE', 'REJECTED'],
        'ACTIVE' => ['SUPERSEDED'],
        'SUPERSEDED' => [],
        'REJECTED' => [],
    ];

    public function assertTransition(string $from, string $to): void
    {
        if (!isset(self::TRANSITIONS[$from]) || !in_array($to, self::TRANSITIONS[$from], true)) {
            throw new InvalidArgumentException('Qur’an release lifecycle transition is not permitted.');
        }
    }

    public function isTerminal(string $status): bool
    {
        return isset(self::TRANSITIONS[$status]) && self::TRANSITIONS[$status] === [];
    }
}
