<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Infrastructure\Persistence;

use DateTimeImmutable;
use PDO;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditCheckpointResult;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditHashChain;
use Qmdb\Modules\SecurityAudit\Configuration\SecurityAuditConfiguration;
use Qmdb\Modules\SecurityAudit\Domain\SecurityAuditIntegrityKeyProvider;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Database\Result\DatabaseResult;
use Qmdb\Shared\Database\Transaction\TransactionManager;
use Qmdb\Shared\Identifier\UuidV7;
use Qmdb\Shared\Time\Clock;

final readonly class SecurityAuditCheckpointService
{
    public function __construct(
        private DatabaseConnectionProvider $provider,
        private TransactionManager $transactions,
        private SecurityAuditIntegrityKeyProvider $keys,
        private SecurityAuditConfiguration $configuration,
        private SecurityAuditHashChain $hashChain,
        private Clock $clock,
    ) {
    }

    public function createWhenChanged(): SecurityAuditCheckpointResult
    {
        $connection = $this->provider->connection();
        $lock = self::statement($connection, "SELECT GET_LOCK('qmdb.security_audit.checkpoint', 10)");
        $lock->execute();
        if (DatabaseResult::integer($lock->fetchColumn(), 'Security audit checkpoint lock') !== 1) {
            throw new \RuntimeException('Security audit checkpoint lock is unavailable.');
        }
        try {
            return $this->transactions->transactional(fn (): SecurityAuditCheckpointResult => $this->create($connection));
        } finally {
            $release = self::statement($connection, "SELECT RELEASE_LOCK('qmdb.security_audit.checkpoint')");
            $release->execute();
        }
    }

    private function create(PDO $connection): SecurityAuditCheckpointResult
    {
        $latest = $connection->query(
            'SELECT id, checkpoint_number, heads_digest, total_event_count, checkpoint_hash FROM security_audit_checkpoints '
            . 'ORDER BY checkpoint_number DESC LIMIT 1 FOR UPDATE',
        );
        $previous = $latest instanceof \PDOStatement
            ? DatabaseResult::nullableRow($latest->fetch(PDO::FETCH_ASSOC), 'Security audit checkpoint')
            : null;
        $streams = self::statement(
            $connection,
            'SELECT id, public_id, stream_type, scope_public_id, last_sequence, last_event_hash FROM security_audit_streams '
            . 'ORDER BY public_id ASC LIMIT :limit',
        );
        $streams->bindValue(':limit', $this->configuration->checkpointMaximumStreams + 1, PDO::PARAM_INT);
        $streams->execute();
        $heads = DatabaseResult::rows($streams->fetchAll(PDO::FETCH_ASSOC), 'Security audit checkpoint stream head');
        if (count($heads) > $this->configuration->checkpointMaximumStreams) {
            throw new \RuntimeException('Security audit checkpoint stream limit is exceeded.');
        }
        $eventCount = DatabaseResult::integer(
            self::query($connection, 'SELECT COUNT(*) FROM security_audit_events')->fetchColumn(),
            'Security audit event count',
        );
        $digestInput = [];
        foreach ($heads as $head) {
            $digestInput[] = [
                'stream_public_id' => UuidV7::fromBinary(DatabaseResult::string($head['public_id'] ?? null, 'Security audit stream public ID'))->toString(),
                'stream_type' => DatabaseResult::string($head['stream_type'] ?? null, 'Security audit stream type'),
                'scope_public_id' => self::uuid($head['scope_public_id'] ?? null, 'Security audit stream scope'),
                'last_sequence' => DatabaseResult::integer($head['last_sequence'] ?? null, 'Security audit stream sequence'),
                'last_event_hash' => self::hex($head['last_event_hash'] ?? null, 'Security audit stream event hash'),
            ];
        }
        $headsDigest = $this->hashChain->headsDigest($digestInput);
        if (
            $previous !== null
            && hash_equals(DatabaseResult::string($previous['heads_digest'] ?? null, 'Security audit checkpoint digest'), $headsDigest)
            && DatabaseResult::integer($previous['total_event_count'] ?? null, 'Security audit checkpoint event count') === $eventCount
        ) {
            return new SecurityAuditCheckpointResult(false, null, null, count($heads), $eventCount);
        }
        $now = $this->clock->now();
        $number = $previous === null ? 1 : DatabaseResult::integer($previous['checkpoint_number'] ?? null, 'Security audit checkpoint number') + 1;
        $previousHash = $previous === null
            ? SecurityAuditHashChain::GENESIS_HASH
            : DatabaseResult::string($previous['checkpoint_hash'] ?? null, 'Security audit checkpoint hash');
        $publicId = UuidV7::generate();
        $keyVersion = $this->configuration->integrityKeyVersion;
        $hash = $this->hashChain->checkpointHash(
            $publicId->toString(),
            $number,
            count($heads),
            $eventCount,
            $headsDigest,
            $previousHash,
            $keyVersion,
            $now,
            $this->keys->keyForVersion($keyVersion),
        );
        $timestamp = SecurityAuditHashChain::timestamp($now);
        $insert = $connection->prepare(
            'INSERT INTO security_audit_checkpoints (public_id, checkpoint_number, stream_count, total_event_count, '
            . 'heads_digest, previous_checkpoint_hash, checkpoint_hash, integrity_key_version, created_at) VALUES '
            . '(:public_id, :number, :streams, :events, :digest, :previous, :hash, :version, :created_at)',
        );
        $insert->execute([
            ':public_id' => $publicId->toBinary(), ':number' => $number, ':streams' => count($heads), ':events' => $eventCount,
            ':digest' => $headsDigest, ':previous' => $previousHash, ':hash' => $hash, ':version' => $keyVersion,
            ':created_at' => $timestamp,
        ]);
        $checkpointId = DatabaseResult::integer($connection->lastInsertId(), 'Security audit checkpoint ID');
        $headInsert = self::statement(
            $connection,
            'INSERT INTO security_audit_checkpoint_heads (checkpoint_id, stream_id, stream_sequence, event_hash, created_at) '
            . 'VALUES (:checkpoint_id, :stream_id, :sequence, :event_hash, :created_at)',
        );
        foreach ($heads as $head) {
            $headInsert->bindValue(':checkpoint_id', $checkpointId, PDO::PARAM_INT);
            $headInsert->bindValue(':stream_id', DatabaseResult::integer($head['id'] ?? null, 'Security audit stream ID'), PDO::PARAM_INT);
            $headInsert->bindValue(':sequence', DatabaseResult::integer($head['last_sequence'] ?? null, 'Security audit stream sequence'), PDO::PARAM_INT);
            $headInsert->bindValue(':event_hash', $head['last_event_hash'], $head['last_event_hash'] === null ? PDO::PARAM_NULL : PDO::PARAM_LOB);
            $headInsert->bindValue(':created_at', $timestamp);
            $headInsert->execute();
        }

        return new SecurityAuditCheckpointResult(true, $publicId->toString(), $number, count($heads), $eventCount);
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

    private static function uuid(mixed $value, string $context): ?string
    {
        $binary = DatabaseResult::nullableString($value, $context);

        return $binary === null ? null : UuidV7::fromBinary($binary)->toString();
    }

    private static function hex(mixed $value, string $context): ?string
    {
        $binary = DatabaseResult::nullableString($value, $context);

        return $binary === null ? null : bin2hex($binary);
    }
}
