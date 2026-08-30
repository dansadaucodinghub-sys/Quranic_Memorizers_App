<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditControlVerificationReport;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditControlVerifier;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditHashChain;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditVerificationReport;
use Qmdb\Modules\SecurityAudit\Configuration\SecurityAuditConfiguration;
use Qmdb\Modules\SecurityAudit\Domain\SecurityAuditIntegrityKeyProvider;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Database\Result\DatabaseResult;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class SecurityAuditVerifier implements SecurityAuditControlVerifier
{
    public function __construct(
        private DatabaseConnectionProvider $provider,
        private SecurityAuditIntegrityKeyProvider $keys,
        private SecurityAuditHashChain $hashChain,
        private SecurityAuditConfiguration $configuration,
    ) {
    }

    public function verify(): SecurityAuditVerificationReport
    {
        $connection = $this->provider->connection();
        $streams = self::query(
            $connection,
            'SELECT id, public_id, stream_type, stream_key, scope_public_id, last_sequence, last_event_hash FROM security_audit_streams ORDER BY id',
        );
        $streamCount = 0;
        $eventCount = 0;
        $errors = [];
        while (($streamRow = $streams->fetch(PDO::FETCH_ASSOC)) !== false) {
            $stream = DatabaseResult::row($streamRow, 'Security audit stream');
            $streamCount++;
            $scope = $this->uuid($stream['scope_public_id'] ?? null) ?? 'platform';
            $streamType = $this->string($stream, 'stream_type');
            $expectedKey = hash('sha256', "QMDB-AUDIT-STREAM-V1\\0{$streamType}\\0{$scope}", true);
            if (!hash_equals($expectedKey, $this->string($stream, 'stream_key'))) {
                $errors[] = 'AUDIT_STREAM_KEY_INVALID';
            }
            $previous = SecurityAuditHashChain::GENESIS_HASH;
            $sequence = 0;
            $events = self::statement(
                $connection,
                'SELECT public_id, sequence_number, event_code, severity, outcome, actor_kind, actor_account_public_id, '
                . 'session_public_id, workspace_public_id, subject_kind, subject_public_id, reason_code, request_id, '
                . 'correlation_id, metadata_canonical_json, metadata_hash, previous_event_hash, event_hash, '
                . 'integrity_key_version, occurred_at FROM security_audit_events WHERE stream_id = :stream_id ORDER BY sequence_number',
            );
            $events->execute([':stream_id' => $this->integer($stream, 'id')]);
            while (($eventRow = $events->fetch(PDO::FETCH_ASSOC)) !== false) {
                $event = DatabaseResult::row($eventRow, 'Security audit event');
                $eventCount++;
                $sequence++;
                if ($this->integer($event, 'sequence_number') !== $sequence || !hash_equals($previous, $this->string($event, 'previous_event_hash'))) {
                    $errors[] = 'AUDIT_EVENT_CHAIN_INVALID';
                    break;
                }
                $metadataHash = hash('sha256', $this->string($event, 'metadata_canonical_json'), true);
                if (!hash_equals($metadataHash, $this->string($event, 'metadata_hash'))) {
                    $errors[] = 'AUDIT_METADATA_HASH_INVALID';
                    break;
                }
                try {
                    $occurredAt = new DateTimeImmutable($this->string($event, 'occurred_at'), new DateTimeZone('UTC'));
                    $fields = [
                        'actor_account_public_id' => $this->uuid($event['actor_account_public_id'] ?? null),
                        'actor_kind' => $this->string($event, 'actor_kind'),
                        'correlation_id' => $this->correlation($event['correlation_id'] ?? null),
                        'event_code' => $this->string($event, 'event_code'),
                        'event_public_id' => $this->uuid($event['public_id'] ?? null),
                        'integrity_key_version' => $this->integer($event, 'integrity_key_version'),
                        'occurred_at' => SecurityAuditHashChain::timestamp($occurredAt),
                        'outcome' => $this->string($event, 'outcome'),
                        'reason_code' => $this->nullableString($event['reason_code'] ?? null),
                        'request_id' => $this->uuid($event['request_id'] ?? null),
                        'sequence_number' => $this->integer($event, 'sequence_number'),
                        'session_public_id' => $this->uuid($event['session_public_id'] ?? null),
                        'severity' => $this->string($event, 'severity'),
                        'stream_public_id' => $this->uuid($stream['public_id'] ?? null),
                        'stream_type' => $streamType,
                        'subject_kind' => $this->string($event, 'subject_kind'),
                        'subject_public_id' => $this->uuid($event['subject_public_id'] ?? null),
                        'workspace_public_id' => $this->uuid($event['workspace_public_id'] ?? null),
                    ];
                    $expected = $this->hashChain->eventHash(
                        $fields,
                        $metadataHash,
                        $previous,
                        $this->keys->keyForVersion($this->integer($event, 'integrity_key_version')),
                    );
                    if (!hash_equals($expected, $this->string($event, 'event_hash'))) {
                        $errors[] = 'AUDIT_EVENT_HMAC_INVALID';
                        break;
                    }
                } catch (\Throwable) {
                    $errors[] = 'AUDIT_EVENT_KEY_OR_FORMAT_INVALID';
                    break;
                }
                $previous = $this->string($event, 'event_hash');
            }
            if (
                $this->integer($stream, 'last_sequence') !== $sequence
                || ($sequence > 0 && !hash_equals($previous, $this->string($stream, 'last_event_hash')))
            ) {
                $errors[] = 'AUDIT_STREAM_HEAD_INVALID';
            }
        }
        $checkpoints = $this->verifyCheckpoints($connection, $errors, $eventCount);
        $controls = $this->verifyControls();
        $errors = [...$errors, ...$controls->errors];

        return new SecurityAuditVerificationReport($streamCount, $eventCount, $checkpoints, array_values(array_unique($errors)));
    }

    public function verifyControls(): SecurityAuditControlVerificationReport
    {
        $errors = [];
        try {
            $this->keys->keyForVersion($this->configuration->integrityKeyVersion);
        } catch (\Throwable) {
            $errors[] = 'AUDIT_INTEGRITY_KEY_UNAVAILABLE';
        }
        try {
            $this->verifyDatabaseControls($this->provider->connection(), $errors);
        } catch (\Throwable) {
            $errors[] = 'AUDIT_DATABASE_CONTROLS_UNAVAILABLE';
        }

        return new SecurityAuditControlVerificationReport(array_values(array_unique($errors)));
    }

    /** @param list<string> $errors */
    private function verifyCheckpoints(PDO $connection, array &$errors, int $currentEventCount): int
    {
        $checkpoints = self::query(
            $connection,
            'SELECT id, public_id, checkpoint_number, stream_count, total_event_count, heads_digest, previous_checkpoint_hash, '
            . 'checkpoint_hash, integrity_key_version, created_at FROM security_audit_checkpoints ORDER BY checkpoint_number ASC',
        );
        $previous = SecurityAuditHashChain::GENESIS_HASH;
        $number = 0;
        $count = 0;
        while (($checkpointRow = $checkpoints->fetch(PDO::FETCH_ASSOC)) !== false) {
            $checkpoint = DatabaseResult::row($checkpointRow, 'Security audit checkpoint');
            $count++;
            $number++;
            if (
                $this->integer($checkpoint, 'checkpoint_number') !== $number
                || !hash_equals($previous, $this->string($checkpoint, 'previous_checkpoint_hash'))
            ) {
                $errors[] = 'AUDIT_CHECKPOINT_CHAIN_INVALID';
                break;
            }
            $heads = $this->checkpointHeads($connection, $this->integer($checkpoint, 'id'), $errors);
            $snapshotEventCount = array_sum(array_map(static fn (array $head): int => $head['last_sequence'], $heads));
            if (
                count($heads) !== $this->integer($checkpoint, 'stream_count')
                || $snapshotEventCount !== $this->integer($checkpoint, 'total_event_count')
                || !hash_equals($this->hashChain->headsDigest($heads), $this->string($checkpoint, 'heads_digest'))
            ) {
                $errors[] = 'AUDIT_CHECKPOINT_HEADS_INVALID';
                break;
            }
            if ($this->integer($checkpoint, 'total_event_count') > $currentEventCount) {
                $errors[] = 'AUDIT_CHECKPOINT_EVENT_COUNT_INVALID';
                break;
            }
            try {
                $createdAt = new DateTimeImmutable($this->string($checkpoint, 'created_at'), new DateTimeZone('UTC'));
                $expected = $this->hashChain->checkpointHash(
                    $this->uuid($checkpoint['public_id'] ?? null) ?? '',
                    $this->integer($checkpoint, 'checkpoint_number'),
                    $this->integer($checkpoint, 'stream_count'),
                    $this->integer($checkpoint, 'total_event_count'),
                    $this->string($checkpoint, 'heads_digest'),
                    $previous,
                    $this->integer($checkpoint, 'integrity_key_version'),
                    $createdAt,
                    $this->keys->keyForVersion($this->integer($checkpoint, 'integrity_key_version')),
                );
                if (!hash_equals($expected, $this->string($checkpoint, 'checkpoint_hash'))) {
                    $errors[] = 'AUDIT_CHECKPOINT_HMAC_INVALID';
                    break;
                }
            } catch (\Throwable) {
                $errors[] = 'AUDIT_CHECKPOINT_KEY_OR_FORMAT_INVALID';
                break;
            }
            $previous = $this->string($checkpoint, 'checkpoint_hash');
        }

        return $count;
    }

    /**
     * @param list<string> $errors
     * @return list<array{stream_public_id:string,stream_type:string,scope_public_id:?string,last_sequence:int,last_event_hash:?string}>
     */
    private function checkpointHeads(PDO $connection, int $checkpointId, array &$errors): array
    {
        $statement = self::statement(
            $connection,
            'SELECT head.stream_id, stream.public_id, stream.stream_type, stream.scope_public_id, head.stream_sequence, head.event_hash '
            . 'FROM security_audit_checkpoint_heads head INNER JOIN security_audit_streams stream ON stream.id = head.stream_id '
            . 'WHERE head.checkpoint_id = :checkpoint_id ORDER BY stream.public_id ASC',
        );
        $statement->execute([':checkpoint_id' => $checkpointId]);
        $heads = [];
        while (($rowValue = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $row = DatabaseResult::row($rowValue, 'Security audit checkpoint head');
            $sequence = $this->integer($row, 'stream_sequence');
            $eventHash = $this->nullableString($row['event_hash'] ?? null);
            if (($sequence === 0) !== ($eventHash === null)) {
                $errors[] = 'AUDIT_CHECKPOINT_HEAD_SEQUENCE_INVALID';
                continue;
            }
            if ($sequence > 0) {
                $event = self::statement(
                    $connection,
                    'SELECT event_hash FROM security_audit_events WHERE stream_id = :stream_id AND sequence_number = :sequence LIMIT 1',
                );
                $event->execute([':stream_id' => $this->integer($row, 'stream_id'), ':sequence' => $sequence]);
                $persistedHash = $event->fetchColumn();
                if (!is_string($persistedHash) || !hash_equals($persistedHash, $eventHash ?? '')) {
                    $errors[] = 'AUDIT_CHECKPOINT_SNAPSHOT_EVENT_INVALID';
                    continue;
                }
            }
            $heads[] = [
                'stream_public_id' => UuidV7::fromBinary($this->string($row, 'public_id'))->toString(),
                'stream_type' => $this->string($row, 'stream_type'),
                'scope_public_id' => $this->uuid($row['scope_public_id'] ?? null),
                'last_sequence' => $sequence,
                'last_event_hash' => $eventHash === null ? null : bin2hex($eventHash),
            ];
        }

        return $heads;
    }

    /** @param list<string> $errors */
    private function verifyDatabaseControls(PDO $connection, array &$errors): void
    {
        $requiredIndexes = [
            'security_audit_streams' => ['uq_security_audit_streams_stream_key', 'ix_security_audit_streams_type_scope'],
            'security_audit_events' => [
                'uq_security_audit_events_stream_sequence', 'ix_security_audit_events_stream_occurred',
                'ix_security_audit_events_code_occurred', 'ix_security_audit_events_actor_occurred',
                'ix_security_audit_events_subject_occurred', 'ix_security_audit_events_workspace_occurred',
                'ix_security_audit_events_severity_occurred',
            ],
            'security_audit_checkpoints' => ['uq_security_audit_checkpoints_number'],
        ];
        foreach ($requiredIndexes as $table => $indexes) {
            $statement = self::statement(
                $connection,
                'SELECT index_name FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = :table',
            );
            $statement->execute([':table' => $table]);
            $actual = array_fill_keys(self::stringValues($statement->fetchAll(PDO::FETCH_COLUMN), 'Security audit index'), true);
            foreach ($indexes as $index) {
                if (!isset($actual[$index])) {
                    $errors[] = 'AUDIT_REQUIRED_INDEX_MISSING';
                }
            }
        }
        $triggers = self::query(
            $connection,
            "SELECT trigger_name FROM information_schema.triggers WHERE trigger_schema = DATABASE() AND trigger_name LIKE 'trg_security_audit_%'",
        );
        $actualTriggers = array_fill_keys(self::stringValues($triggers->fetchAll(PDO::FETCH_COLUMN), 'Security audit trigger'), true);
        foreach (
            [
            'trg_security_audit_streams_no_delete', 'trg_security_audit_events_no_update',
            'trg_security_audit_events_no_delete', 'trg_security_audit_checkpoints_no_update',
            'trg_security_audit_checkpoints_no_delete', 'trg_security_audit_checkpoint_heads_no_update',
            'trg_security_audit_checkpoint_heads_no_delete',
            ] as $trigger
        ) {
            if (!isset($actualTriggers[$trigger])) {
                $errors[] = 'AUDIT_IMMUTABILITY_TRIGGER_MISSING';
            }
        }
    }

    private function uuid(mixed $value): ?string
    {
        $binary = $this->nullableString($value);

        return $binary === null ? null : UuidV7::fromBinary($binary)->toString();
    }

    private function correlation(mixed $value): ?string
    {
        $binary = $this->nullableString($value);

        return $binary === null ? null : bin2hex($binary);
    }

    /** @param array<string, mixed> $row */
    private function string(array $row, string $column): string
    {
        return DatabaseResult::string($row[$column] ?? null, 'Security audit ' . $column);
    }

    /** @param array<string, mixed> $row */
    private function integer(array $row, string $column): int
    {
        return DatabaseResult::integer($row[$column] ?? null, 'Security audit ' . $column);
    }

    private function nullableString(mixed $value): ?string
    {
        return DatabaseResult::nullableString($value, 'Security audit value');
    }

    private static function statement(PDO $connection, string $sql): \PDOStatement
    {
        $statement = $connection->prepare($sql);
        if (!$statement instanceof \PDOStatement) {
            throw new \RuntimeException('Security audit statement preparation failed.');
        }

        return $statement;
    }

    private static function query(PDO $connection, string $sql): \PDOStatement
    {
        $statement = $connection->query($sql);
        if (!$statement instanceof \PDOStatement) {
            throw new \RuntimeException('Security audit query failed.');
        }

        return $statement;
    }

    /**
     * @param array<array-key, mixed> $values
     * @return list<string>
     */
    private static function stringValues(array $values, string $context): array
    {
        $result = [];
        foreach ($values as $value) {
            $result[] = DatabaseResult::string($value, $context);
        }

        return $result;
    }
}
