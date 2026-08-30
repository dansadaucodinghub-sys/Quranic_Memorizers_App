<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Application;

use DateTimeImmutable;
use Qmdb\Modules\SecurityAudit\Domain\SecurityAuditStreamIdentity;
use Qmdb\Modules\SecurityAudit\Domain\SecurityAuditStreamType;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventActorKind;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventOutcome;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventSubjectKind;
use Qmdb\Shared\Identifier\UuidV7;

/**
 * Generic boundary helper: it accepts only shared UUID values, never identity-module value objects.
 * Callers must invoke it within their authoritative database transaction.
 */
final readonly class SecurityAuditEventAppender
{
    public function __construct(private SecurityAuditRecorder $recorder)
    {
    }

    /** @param array<string, mixed> $metadata */
    public function account(
        SecurityEventCode $code,
        string $subjectAccountPublicId,
        ?string $actorAccountPublicId,
        ?string $sessionPublicId,
        DateTimeImmutable $occurredAt,
        array $metadata = [],
        ?string $reasonCode = null,
        ?string $correlationId = null,
    ): SecurityAuditAppendResult {
        return $this->append(
            new SecurityAuditStreamIdentity(SecurityAuditStreamType::ACCOUNT, UuidV7::fromString($subjectAccountPublicId)),
            $code,
            SecurityEventSubjectKind::ACCOUNT,
            $subjectAccountPublicId,
            $actorAccountPublicId,
            $sessionPublicId,
            null,
            $occurredAt,
            $metadata,
            $reasonCode,
            $correlationId,
        );
    }

    /** @param array<string, mixed> $metadata */
    public function platform(
        SecurityEventCode $code,
        SecurityEventSubjectKind $subjectKind,
        string $subjectPublicId,
        ?string $actorAccountPublicId,
        DateTimeImmutable $occurredAt,
        array $metadata = [],
        ?string $reasonCode = null,
        ?string $correlationId = null,
    ): SecurityAuditAppendResult {
        return $this->append(
            SecurityAuditStreamIdentity::platform(),
            $code,
            $subjectKind,
            $subjectPublicId,
            $actorAccountPublicId,
            null,
            null,
            $occurredAt,
            $metadata,
            $reasonCode,
            $correlationId,
        );
    }

    /** @param array<string, mixed> $metadata */
    public function workspace(
        SecurityEventCode $code,
        string $workspacePublicId,
        SecurityEventSubjectKind $subjectKind,
        string $subjectPublicId,
        ?string $actorAccountPublicId,
        DateTimeImmutable $occurredAt,
        array $metadata = [],
        ?string $reasonCode = null,
        ?string $correlationId = null,
    ): SecurityAuditAppendResult {
        return $this->append(
            new SecurityAuditStreamIdentity(SecurityAuditStreamType::WORKSPACE, UuidV7::fromString($workspacePublicId)),
            $code,
            $subjectKind,
            $subjectPublicId,
            $actorAccountPublicId,
            null,
            $workspacePublicId,
            $occurredAt,
            $metadata,
            $reasonCode,
            $correlationId,
        );
    }

    /** @param array<string, mixed> $metadata */
    public function privilegedAccess(
        SecurityEventCode $code,
        bool $workspaceScoped,
        ?string $workspacePublicId,
        SecurityEventSubjectKind $subjectKind,
        string $subjectPublicId,
        ?string $actorAccountPublicId,
        DateTimeImmutable $occurredAt,
        array $metadata = [],
        ?string $reasonCode = null,
        ?string $correlationId = null,
    ): SecurityAuditAppendResult {
        if ($workspaceScoped) {
            if ($workspacePublicId === null) {
                throw new \UnexpectedValueException('Workspace-scoped privileged access requires a workspace audit stream.');
            }

            return $this->workspace(
                $code,
                $workspacePublicId,
                $subjectKind,
                $subjectPublicId,
                $actorAccountPublicId,
                $occurredAt,
                $metadata,
                $reasonCode,
                $correlationId,
            );
        }

        return $this->platform(
            $code,
            $subjectKind,
            $subjectPublicId,
            $actorAccountPublicId,
            $occurredAt,
            $metadata,
            $reasonCode,
            $correlationId,
        );
    }

    /** @param array<string, mixed> $metadata */
    private function append(
        SecurityAuditStreamIdentity $stream,
        SecurityEventCode $code,
        SecurityEventSubjectKind $subjectKind,
        string $subjectPublicId,
        ?string $actorAccountPublicId,
        ?string $sessionPublicId,
        ?string $workspacePublicId,
        DateTimeImmutable $occurredAt,
        array $metadata,
        ?string $reasonCode,
        ?string $correlationId,
    ): SecurityAuditAppendResult {
        return $this->recorder->append(new SecurityAuditAppendCommand(
            $stream,
            $code,
            SecurityEventOutcome::SUCCESS,
            $actorAccountPublicId === null ? SecurityEventActorKind::SYSTEM : SecurityEventActorKind::ACCOUNT,
            $actorAccountPublicId === null ? null : UuidV7::fromString($actorAccountPublicId),
            $sessionPublicId === null ? null : UuidV7::fromString($sessionPublicId),
            $workspacePublicId === null ? null : UuidV7::fromString($workspacePublicId),
            $subjectKind,
            UuidV7::fromString($subjectPublicId),
            $reasonCode,
            null,
            $correlationId,
            $metadata,
            $occurredAt,
        ));
    }
}
