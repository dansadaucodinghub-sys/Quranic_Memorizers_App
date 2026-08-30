<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Application;

use Qmdb\Modules\SecurityAudit\Domain\SecurityAuditStreamType;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventOutcome;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventSeverity;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class SecurityAuditListFilter
{
    public function __construct(
        public ?SecurityEventCode $eventCode = null,
        public ?SecurityEventSeverity $severity = null,
        public ?SecurityEventOutcome $outcome = null,
        public ?SecurityAuditStreamType $streamType = null,
        public ?string $subjectPublicId = null,
        public ?string $workspacePublicId = null,
    ) {
        foreach ([$subjectPublicId, $workspacePublicId] as $identifier) {
            if ($identifier !== null) {
                UuidV7::fromString($identifier);
            }
        }
    }
}
