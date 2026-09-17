<?php

declare(strict_types=1);

namespace Qmdb\Modules\MediaCatalog\Domain;

/**
 * Closed, server-authoritative P9 evidence lifecycle.  The lifecycle deliberately
 * has no generic setter: callers must ask whether a specific transition is valid.
 */
final class MediaLifecycle
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'STAGING' => ['QUARANTINED', 'REJECTED', 'WITHDRAWN'],
        'QUARANTINED' => ['SCANNING', 'REJECTED', 'WITHDRAWN'],
        'SCANNING' => ['PROCESSING', 'REJECTED', 'QUARANTINED', 'WITHDRAWN'],
        'PROCESSING' => ['PENDING_MODERATION', 'APPROVED', 'REJECTED', 'QUARANTINED', 'WITHDRAWN'],
        'PENDING_MODERATION' => ['APPROVED', 'REJECTED', 'WITHDRAWN'],
        'APPROVED' => ['PUBLISHED', 'WITHDRAWN', 'ARCHIVED'],
        'PUBLISHED' => ['WITHDRAWN', 'ARCHIVED'],
        'REJECTED' => ['ARCHIVED'],
        'WITHDRAWN' => ['ARCHIVED'],
        'ARCHIVED' => [],
    ];

    public function assertTransition(string $from, string $to): void
    {
        if (!isset(self::TRANSITIONS[$from]) || !in_array($to, self::TRANSITIONS[$from], true)) {
            throw new \DomainException('Media lifecycle transition is not permitted.');
        }
    }

    public function isDeliverable(string $status, bool $rightsGranted, bool $consentGranted, bool $held): bool
    {
        return in_array($status, ['APPROVED', 'PUBLISHED'], true) && $rightsGranted && $consentGranted && !$held;
    }
}
