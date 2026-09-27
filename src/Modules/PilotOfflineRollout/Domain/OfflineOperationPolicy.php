<?php

declare(strict_types=1);

namespace Qmdb\Modules\PilotOfflineRollout\Domain;

final readonly class OfflineOperationPolicy
{
    /** @var list<string> */
    public const array ALLOWED = [
        'PARTICIPANT_CHECK_IN',
        'PARTICIPANT_ABSENT',
        'PARTICIPANT_CALLED',
        'PARTICIPANT_READY',
        'PERFORMANCE_STARTED',
        'PERFORMANCE_INTERRUPTED',
        'PERFORMANCE_RESUMED',
        'PERFORMANCE_COMPLETED',
        'SCORE_DRAFT_SAVED',
        'SCORE_SHEET_SUBMITTED',
        'VENUE_INCIDENT_RECORDED',
        'OPERATIONAL_NOTE_RECORDED',
        'JUDGE_ACKNOWLEDGEMENT_RECORDED',
    ];

    /** @var list<string> */
    private const array PROHIBITED_FIELDS = [
        'password', 'session_token', 'csrf_token', 'mfa_secret', 'recovery_code', 'private_key', 'role_id',
        'permission_id', 'result_status', 'certificate_status', 'signing_key', 'sql', 'class_name', 'expression',
    ];

    /** @param array<string, mixed> $payload */
    public function assertAllowed(string $operation, array $payload, int $maximumBytes = 32768): void
    {
        if (!in_array($operation, self::ALLOWED, true)) {
            throw new \DomainException('Offline operation is not allowlisted.');
        }
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (strlen($encoded) > $maximumBytes) {
            throw new \OverflowException('Offline operation payload exceeds the configured limit.');
        }
        $keys = array_map('strtolower', array_keys($payload));
        if (array_intersect($keys, self::PROHIBITED_FIELDS) !== []) {
            throw new \DomainException('Offline operation contains a prohibited field.');
        }
    }
}
