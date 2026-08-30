<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Application;

use DateTimeImmutable;
use Qmdb\Modules\SecurityAudit\Domain\SecurityAuditStreamType;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventOutcome;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventSeverity;

/** Deliberately omits raw metadata, internal IDs, contacts, tokens, and justification text. */
final readonly class SecurityAuditEventRecord
{
    public function __construct(
        public string $publicId,
        public string $eventCode,
        public SecurityEventSeverity $severity,
        public SecurityEventOutcome $outcome,
        public SecurityAuditStreamType $streamType,
        public ?string $streamScopePublicId,
        public ?string $actorAccountPublicId,
        public string $subjectKind,
        public string $subjectPublicId,
        public ?string $workspacePublicId,
        public ?string $reasonCode,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
