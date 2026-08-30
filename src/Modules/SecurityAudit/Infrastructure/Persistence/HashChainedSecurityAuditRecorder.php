<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Infrastructure\Persistence;

use PDO;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditAppendCommand;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditAppendResult;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditHashChain;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditRecorder;
use Qmdb\Modules\SecurityAudit\Configuration\SecurityAuditConfiguration;
use Qmdb\Modules\SecurityAudit\Domain\CanonicalSecurityEventMetadataSerializer;
use Qmdb\Modules\SecurityAudit\Domain\SecurityAuditIntegrityKeyProvider;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Database\Result\DatabaseResult;
use Qmdb\Shared\Identifier\UuidV7;

/**
 * Appends only through the caller's already-active transaction.  It never starts or commits one.
 */
final readonly class HashChainedSecurityAuditRecorder implements SecurityAuditRecorder
{
    public function __construct(
        private DatabaseConnectionProvider $provider,
        private CanonicalSecurityEventMetadataSerializer $metadata,
        private SecurityAuditIntegrityKeyProvider $keys,
        private SecurityAuditConfiguration $configuration,
        private SecurityAuditHashChain $hashChain,
    ) {
    }

    public function append(SecurityAuditAppendCommand $command): SecurityAuditAppendResult
    {
        $connection = $this->provider->connection();
        if (!$connection->inTransaction()) {
            throw new \LogicException('Security audit append requires the authoritative transaction.');
        }
        $stream = $this->lockOrCreateStream($connection, $command);
        $sequence = DatabaseResult::integer($stream['last_sequence'] ?? null, 'Security audit stream sequence') + 1;
        $previous = $stream['last_event_hash'] === null
            ? SecurityAuditHashChain::GENESIS_HASH
            : DatabaseResult::string($stream['last_event_hash'], 'Security audit stream event hash');
        $canonical = $this->metadata->serialize($command->metadata);
        $metadataHash = hash('sha256', $canonical, true);
        $eventId = UuidV7::generate();
        $keyVersion = $this->configuration->integrityKeyVersion;
        $eventHash = $this->hashChain->eventHash($this->eventFields($command, $stream, $sequence, $eventId, $keyVersion), $metadataHash, $previous, $this->keys->keyForVersion($keyVersion));
        $now = SecurityAuditHashChain::timestamp($command->occurredAt);
        $insert = $connection->prepare(
            'INSERT INTO security_audit_events (public_id, stream_id, sequence_number, event_code, severity, outcome, '
            . 'actor_kind, actor_account_public_id, session_public_id, workspace_public_id, subject_kind, subject_public_id, '
            . 'reason_code, request_id, correlation_id, metadata_canonical_json, metadata_hash, previous_event_hash, event_hash, '
            . 'integrity_key_version, occurred_at, created_at) VALUES '
            . '(:public_id, :stream_id, :sequence, :event_code, :severity, :outcome, :actor_kind, :actor_account_id, '
            . ':session_id, :workspace_id, :subject_kind, :subject_id, :reason_code, :request_id, :correlation_id, '
            . ':metadata, :metadata_hash, :previous_hash, :event_hash, :key_version, :occurred_at, :created_at)',
        );
        $insert->bindValue(':public_id', $eventId->toBinary(), PDO::PARAM_LOB);
        $insert->bindValue(':stream_id', DatabaseResult::integer($stream['id'] ?? null, 'Security audit stream ID'), PDO::PARAM_INT);
        $insert->bindValue(':sequence', $sequence, PDO::PARAM_INT);
        $insert->bindValue(':event_code', $command->eventCode->value);
        $insert->bindValue(':severity', $command->eventCode->severity()->value);
        $insert->bindValue(':outcome', $command->outcome->value);
        $insert->bindValue(':actor_kind', $command->actorKind->value);
        $this->binary($insert, ':actor_account_id', $command->actorAccountPublicId?->toBinary());
        $this->binary($insert, ':session_id', $command->sessionPublicId?->toBinary());
        $this->binary($insert, ':workspace_id', $command->workspacePublicId?->toBinary());
        $insert->bindValue(':subject_kind', $command->subjectKind->value);
        $insert->bindValue(':subject_id', $command->subjectPublicId->toBinary(), PDO::PARAM_LOB);
        $insert->bindValue(':reason_code', $command->reasonCode);
        $this->binary($insert, ':request_id', $command->requestId?->toBinary());
        $this->binary($insert, ':correlation_id', $command->correlationId === null ? null : $this->correlationBinary($command->correlationId));
        $insert->bindValue(':metadata', $canonical);
        $insert->bindValue(':metadata_hash', $metadataHash, PDO::PARAM_LOB);
        $insert->bindValue(':previous_hash', $previous, PDO::PARAM_LOB);
        $insert->bindValue(':event_hash', $eventHash, PDO::PARAM_LOB);
        $insert->bindValue(':key_version', $keyVersion, PDO::PARAM_INT);
        $insert->bindValue(':occurred_at', $now);
        $insert->bindValue(':created_at', $now);
        $insert->execute();
        $head = $connection->prepare(
            'UPDATE security_audit_streams SET last_sequence = :sequence, last_event_hash = :event_hash, '
            . 'version = version + 1, updated_at = :updated_at WHERE id = :id AND version = :version',
        );
        $head->bindValue(':sequence', $sequence, PDO::PARAM_INT);
        $head->bindValue(':event_hash', $eventHash, PDO::PARAM_LOB);
        $head->bindValue(':updated_at', $now);
        $head->bindValue(':id', DatabaseResult::integer($stream['id'] ?? null, 'Security audit stream ID'), PDO::PARAM_INT);
        $head->bindValue(':version', DatabaseResult::integer($stream['version'] ?? null, 'Security audit stream version'), PDO::PARAM_INT);
        $head->execute();
        if ($head->rowCount() !== 1) {
            throw new \RuntimeException('Security audit stream head changed concurrently.');
        }

        return new SecurityAuditAppendResult(
            $eventId->toString(),
            UuidV7::fromBinary(DatabaseResult::string($stream['public_id'] ?? null, 'Security audit stream public ID'))->toString(),
            $sequence,
        );
    }

    /** @return array<string, mixed> */
    private function lockOrCreateStream(PDO $connection, SecurityAuditAppendCommand $command): array
    {
        $identity = $command->stream;
        $key = $identity->key();
        $find = static function () use ($connection, $key): ?array {
            $statement = $connection->prepare('SELECT id, public_id, stream_type, last_sequence, last_event_hash, version FROM security_audit_streams WHERE stream_key = :stream_key FOR UPDATE');
            $statement->bindValue(':stream_key', $key, PDO::PARAM_LOB);
            $statement->execute();
            return DatabaseResult::nullableRow($statement->fetch(PDO::FETCH_ASSOC), 'Security audit stream');
        };
        $stream = $find();
        if ($stream !== null) {
            return $stream;
        }
        $now = SecurityAuditHashChain::timestamp($command->occurredAt);
        $insert = $connection->prepare(
            'INSERT IGNORE INTO security_audit_streams (public_id, stream_type, stream_key, scope_public_id, '
            . 'last_sequence, last_event_hash, version, created_at, updated_at) VALUES '
            . '(:public_id, :type, :stream_key, :scope_public_id, 0, NULL, 1, :created_at, :updated_at)',
        );
        $insert->bindValue(':public_id', UuidV7::generate()->toBinary(), PDO::PARAM_LOB);
        $insert->bindValue(':type', $identity->type->value);
        $insert->bindValue(':stream_key', $key, PDO::PARAM_LOB);
        $this->binary($insert, ':scope_public_id', $identity->scopePublicId?->toBinary());
        $insert->bindValue(':created_at', $now);
        $insert->bindValue(':updated_at', $now);
        $insert->execute();
        $stream = $find();
        if ($stream === null) {
            throw new \RuntimeException('Security audit stream cannot be resolved.');
        }

        return $stream;
    }

    /**
     * @param array<string, mixed> $stream
     * @return array<string, bool|float|int|string|null>
     */
    private function eventFields(SecurityAuditAppendCommand $command, array $stream, int $sequence, UuidV7 $event, int $keyVersion): array
    {
        return [
            'actor_account_public_id' => $command->actorAccountPublicId?->toString(), 'actor_kind' => $command->actorKind->value,
            'correlation_id' => $command->correlationId, 'event_code' => $command->eventCode->value,
            'event_public_id' => $event->toString(), 'integrity_key_version' => $keyVersion,
            'occurred_at' => SecurityAuditHashChain::timestamp($command->occurredAt), 'outcome' => $command->outcome->value,
            'reason_code' => $command->reasonCode, 'request_id' => $command->requestId?->toString(),
            'sequence_number' => $sequence, 'session_public_id' => $command->sessionPublicId?->toString(),
            'severity' => $command->eventCode->severity()->value,
            'stream_public_id' => UuidV7::fromBinary(DatabaseResult::string($stream['public_id'] ?? null, 'Security audit stream public ID'))->toString(),
            'stream_type' => DatabaseResult::string($stream['stream_type'] ?? null, 'Security audit stream type'),
            'subject_kind' => $command->subjectKind->value, 'subject_public_id' => $command->subjectPublicId->toString(),
            'workspace_public_id' => $command->workspacePublicId?->toString(),
        ];
    }

    private function binary(\PDOStatement $statement, string $name, ?string $value): void
    {
        if ($value === null) {
            $statement->bindValue($name, null, PDO::PARAM_NULL);
            return;
        }
        $statement->bindValue($name, $value, PDO::PARAM_LOB);
    }

    private function correlationBinary(string $correlationId): string
    {
        $binary = hex2bin($correlationId);
        if (!is_string($binary)) {
            throw new \UnexpectedValueException('Security audit correlation identifier is invalid.');
        }

        return $binary;
    }
}
