<?php

declare(strict_types=1);

namespace Qmdb\Modules\PilotOfflineRollout\Application;

use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventAppender;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventCode;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventSubjectKind;
use Qmdb\Shared\Time\Clock;

/** P13-native operations become authoritative through their immutable submission, receipt, and event transaction. */
final readonly class P13NativeOfflineOperationAdapter
{
    public function __construct(private SecurityAuditEventAppender $audit, private Clock $clock)
    {
    }

    public function apply(OfflineOperationContext $context): OfflineOperationResult
    {
        $allowed = ['VENUE_INCIDENT_RECORDED', 'OPERATIONAL_NOTE_RECORDED', 'JUDGE_ACKNOWLEDGEMENT_RECORDED'];
        if (!in_array($context->operation, $allowed, true)) {
            throw new \InvalidArgumentException('P13-native offline operation is invalid.');
        }
        $this->assertPayload($context);

        $this->audit->workspace(
            SecurityEventCode::P13_OFFLINE_OPERATION_APPLIED,
            $context->tenant->workspacePublicId(),
            SecurityEventSubjectKind::PILOT_OFFLINE_OPERATION,
            $context->entityId->toString(),
            $context->actor->accountId->toString(),
            $this->clock->now(),
            ['operation' => $context->operation, 'version_before' => $context->expectedVersion, 'version_after' => $context->expectedVersion + 1],
            null,
            $context->correlationId,
        );

        return OfflineOperationResult::accepted($context->entityId, $context->expectedVersion + 1, $context->operation);
    }

    private function assertPayload(OfflineOperationContext $context): void
    {
        $expected = match ($context->operation) {
            'VENUE_INCIDENT_RECORDED' => ['classification', 'severity', 'statement'],
            'OPERATIONAL_NOTE_RECORDED' => ['note'],
            'JUDGE_ACKNOWLEDGEMENT_RECORDED' => ['acknowledgement_code', 'assignment_id'],
            default => throw new \InvalidArgumentException('P13-native offline operation is invalid.'),
        };
        $actual = array_keys($context->payload);
        sort($expected);
        sort($actual);
        if ($actual !== $expected) {
            throw new \InvalidArgumentException('P13-native offline payload schema is invalid.');
        }
        if ($context->operation === 'VENUE_INCIDENT_RECORDED') {
            $classification = $context->payload['classification'];
            $severity = $context->payload['severity'];
            $statement = $context->payload['statement'];
            if (
                !is_string($classification) || !in_array($classification, ['SECURITY', 'DATA_INTEGRITY', 'OFFLINE_SYNC', 'DEVICE_FAILURE', 'VENUE_OPERATION', 'ACCESSIBILITY', 'PERFORMANCE', 'NOTIFICATION', 'OTHER_APPROVED'], true)
                || !is_string($severity) || !in_array($severity, ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'], true)
                || !is_string($statement) || trim($statement) === '' || mb_strlen($statement) > 4000
            ) {
                throw new \InvalidArgumentException('Offline venue incident payload is invalid.');
            }

            return;
        }
        if ($context->operation === 'OPERATIONAL_NOTE_RECORDED') {
            $note = $context->payload['note'];
            if (!is_string($note) || trim($note) === '' || mb_strlen($note) > 2000) {
                throw new \InvalidArgumentException('Offline operational note payload is invalid.');
            }

            return;
        }
        $code = $context->payload['acknowledgement_code'];
        if (!is_string($code) || preg_match('/\A[A-Z][A-Z0-9_]{2,63}\z/', $code) !== 1) {
            throw new \InvalidArgumentException('Offline judge acknowledgement payload is invalid.');
        }
        $context->requiredUuid('assignment_id');
    }
}
