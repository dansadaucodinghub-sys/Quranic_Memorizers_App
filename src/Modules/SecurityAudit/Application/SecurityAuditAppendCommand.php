<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Application;

use DateTimeImmutable;
use Qmdb\Modules\SecurityAudit\Domain\SecurityAuditStreamIdentity;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventActorKind;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventOutcome;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventSubjectKind;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class SecurityAuditAppendCommand
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public SecurityAuditStreamIdentity $stream,
        public SecurityEventCode $eventCode,
        public SecurityEventOutcome $outcome,
        public SecurityEventActorKind $actorKind,
        public ?UuidV7 $actorAccountPublicId,
        public ?UuidV7 $sessionPublicId,
        public ?UuidV7 $workspacePublicId,
        public SecurityEventSubjectKind $subjectKind,
        public UuidV7 $subjectPublicId,
        public ?string $reasonCode,
        public ?UuidV7 $requestId,
        public ?string $correlationId,
        public array $metadata,
        public DateTimeImmutable $occurredAt,
    ) {
        if (($actorKind === SecurityEventActorKind::ACCOUNT) !== ($actorAccountPublicId !== null)) {
            throw new \InvalidArgumentException('Security audit actor identity is invalid.');
        }
        if ($correlationId !== null && preg_match('/\A[a-f0-9]{32}\z/D', $correlationId) !== 1) {
            throw new \InvalidArgumentException('Security audit correlation identifier is invalid.');
        }
    }
}
