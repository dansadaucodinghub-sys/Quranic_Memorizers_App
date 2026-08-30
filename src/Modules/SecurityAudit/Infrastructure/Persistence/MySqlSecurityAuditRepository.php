<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAudit\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditEventRecord;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditIntegrityStatus;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditListFilter;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditPage;
use Qmdb\Modules\SecurityAudit\Application\SecurityAuditRepository;
use Qmdb\Modules\SecurityAudit\Domain\SecurityAuditStreamType;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventOutcome;
use Qmdb\Modules\SecurityAudit\Domain\SecurityEventSeverity;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Database\Result\DatabaseResult;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class MySqlSecurityAuditRepository implements SecurityAuditRepository
{
    private const int MAXIMUM_PAGE_SIZE = 100;

    public function __construct(private DatabaseConnectionProvider $provider)
    {
    }

    public function findByPublicId(string $eventPublicId): ?SecurityAuditEventRecord
    {
        $statement = $this->provider->connection()->prepare($this->select() . ' WHERE event.public_id = UUID_TO_BIN(:public_id)');
        $statement->execute([':public_id' => UuidV7::fromString($eventPublicId)->toString()]);
        $row = DatabaseResult::nullableRow($statement->fetch(PDO::FETCH_ASSOC), 'Security audit event');

        return $row === null ? null : $this->record($row);
    }

    public function listForAccount(string $accountPublicId, ?string $cursor, int $limit): SecurityAuditPage
    {
        $accountId = UuidV7::fromString($accountPublicId)->toString();
        $conditions = ['(event.actor_account_public_id = UUID_TO_BIN(:account_id) OR event.subject_public_id = UUID_TO_BIN(:account_id))'];
        $parameters = self::parameters([':account_id' => $accountId]);
        $this->cursorCondition($cursor, $conditions, $parameters);

        return $this->page(implode(' AND ', $conditions), $parameters, $limit);
    }

    public function listPlatform(SecurityAuditListFilter $filter, ?string $cursor, int $limit): SecurityAuditPage
    {
        $conditions = [];
        $parameters = self::parameters();
        foreach (
            [
            'event.event_code' => $filter->eventCode?->value,
            'event.severity' => $filter->severity?->value,
            'event.outcome' => $filter->outcome?->value,
            'stream.stream_type' => $filter->streamType?->value,
            ] as $column => $value
        ) {
            if ($value !== null) {
                $name = ':filter_' . count($parameters);
                $conditions[] = $column . ' = ' . $name;
                $parameters[$name] = $value;
            }
        }
        if ($filter->subjectPublicId !== null) {
            $conditions[] = 'event.subject_public_id = UUID_TO_BIN(:subject_id)';
            $parameters[':subject_id'] = $filter->subjectPublicId;
        }
        if ($filter->workspacePublicId !== null) {
            $conditions[] = 'event.workspace_public_id = UUID_TO_BIN(:workspace_id)';
            $parameters[':workspace_id'] = $filter->workspacePublicId;
        }
        $this->cursorCondition($cursor, $conditions, $parameters);

        return $this->page($conditions === [] ? '1 = 1' : implode(' AND ', $conditions), $parameters, $limit);
    }

    public function integrityStatus(): SecurityAuditIntegrityStatus
    {
        $connection = $this->provider->connection();
        $streamCount = self::count($connection, 'SELECT COUNT(*) FROM security_audit_streams', 'Security audit stream count');
        $eventCount = self::count($connection, 'SELECT COUNT(*) FROM security_audit_events', 'Security audit event count');
        $latest = self::query(
            $connection,
            'SELECT checkpoint_number, checkpoint_hash, created_at FROM security_audit_checkpoints ORDER BY checkpoint_number DESC LIMIT 1',
        );
        $row = DatabaseResult::nullableRow($latest->fetch(PDO::FETCH_ASSOC), 'Security audit checkpoint');
        if ($row === null) {
            return new SecurityAuditIntegrityStatus($streamCount, $eventCount, null, null, null);
        }

        return new SecurityAuditIntegrityStatus(
            $streamCount,
            $eventCount,
            DatabaseResult::integer($row['checkpoint_number'] ?? null, 'Security audit checkpoint number'),
            new DateTimeImmutable(DatabaseResult::string($row['created_at'] ?? null, 'Security audit checkpoint time'), new DateTimeZone('UTC')),
            bin2hex(DatabaseResult::string($row['checkpoint_hash'] ?? null, 'Security audit checkpoint hash')),
        );
    }

    /**
     * @param list<string> $conditions
     * @param array<string, string> $parameters
     */
    private function cursorCondition(?string $cursor, array &$conditions, array &$parameters): void
    {
        if ($cursor === null || $cursor === '') {
            return;
        }
        $normalised = strtr($cursor, '-_', '+/');
        $decoded = base64_decode($normalised . str_repeat('=', (4 - strlen($normalised) % 4) % 4), true);
        $decodedData = is_string($decoded) ? json_decode($decoded, true) : null;
        if (!is_array($decodedData)) {
            throw new \InvalidArgumentException('Security audit cursor is invalid.');
        }
        $data = DatabaseResult::row($decodedData, 'Security audit cursor');
        $publicId = DatabaseResult::string($data['public_id'] ?? null, 'Security audit cursor public ID');
        $occurredAt = new DateTimeImmutable(
            DatabaseResult::string($data['occurred_at'] ?? null, 'Security audit cursor timestamp'),
            new DateTimeZone('UTC'),
        );
        UuidV7::fromString($publicId);
        $conditions[] = '(event.occurred_at < :cursor_occurred_at OR (event.occurred_at = :cursor_occurred_at '
            . 'AND event.public_id < UUID_TO_BIN(:cursor_public_id)))';
        $parameters[':cursor_occurred_at'] = $occurredAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
        $parameters[':cursor_public_id'] = $publicId;
    }

    /** @param array<string, string> $parameters */
    private function page(string $where, array $parameters, int $limit): SecurityAuditPage
    {
        if ($limit < 1 || $limit > self::MAXIMUM_PAGE_SIZE) {
            throw new \InvalidArgumentException('Security audit page limit is invalid.');
        }
        $statement = $this->provider->connection()->prepare($this->select() . ' WHERE ' . $where
            . ' ORDER BY event.occurred_at DESC, event.public_id DESC LIMIT :limit');
        foreach ($parameters as $name => $value) {
            $statement->bindValue($name, $value);
        }
        $statement->bindValue(':limit', $limit + 1, PDO::PARAM_INT);
        $statement->execute();
        $rows = DatabaseResult::rows($statement->fetchAll(PDO::FETCH_ASSOC), 'Security audit event');
        $hasNext = count($rows) > $limit;
        $rows = array_slice($rows, 0, $limit);
        $events = array_map(fn (array $row): SecurityAuditEventRecord => $this->record($row), $rows);
        $last = $events === [] ? null : $events[array_key_last($events)];
        $next = !$hasNext || !$last instanceof SecurityAuditEventRecord ? null : rtrim(strtr(base64_encode(json_encode([
            'occurred_at' => $last->occurredAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
            'public_id' => $last->publicId,
        ], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

        return new SecurityAuditPage($events, $next);
    }

    private function select(): string
    {
        return 'SELECT event.public_id, event.event_code, event.severity, event.outcome, event.actor_account_public_id, '
            . 'event.subject_kind, event.subject_public_id, event.workspace_public_id, event.reason_code, event.occurred_at, '
            . 'stream.stream_type, stream.scope_public_id FROM security_audit_events event '
            . 'INNER JOIN security_audit_streams stream ON stream.id = event.stream_id';
    }

    /** @param array<string, mixed> $row */
    private function record(array $row): SecurityAuditEventRecord
    {
        return new SecurityAuditEventRecord(
            UuidV7::fromBinary(DatabaseResult::string($row['public_id'] ?? null, 'Security audit event public ID'))->toString(),
            DatabaseResult::string($row['event_code'] ?? null, 'Security audit event code'),
            SecurityEventSeverity::from(DatabaseResult::string($row['severity'] ?? null, 'Security audit event severity')),
            SecurityEventOutcome::from(DatabaseResult::string($row['outcome'] ?? null, 'Security audit event outcome')),
            SecurityAuditStreamType::from(DatabaseResult::string($row['stream_type'] ?? null, 'Security audit stream type')),
            $this->uuid($row['scope_public_id'] ?? null),
            $this->uuid($row['actor_account_public_id'] ?? null),
            DatabaseResult::string($row['subject_kind'] ?? null, 'Security audit subject kind'),
            UuidV7::fromBinary(DatabaseResult::string($row['subject_public_id'] ?? null, 'Security audit subject public ID'))->toString(),
            $this->uuid($row['workspace_public_id'] ?? null),
            DatabaseResult::nullableString($row['reason_code'] ?? null, 'Security audit reason code'),
            new DateTimeImmutable(DatabaseResult::string($row['occurred_at'] ?? null, 'Security audit event time'), new DateTimeZone('UTC')),
        );
    }

    private function uuid(mixed $value): ?string
    {
        $binary = DatabaseResult::nullableString($value, 'Security audit UUID');

        return $binary === null ? null : UuidV7::fromBinary($binary)->toString();
    }

    /**
     * @param array<string, string> $parameters
     * @return array<string, string>
     */
    private static function parameters(array $parameters = []): array
    {
        return $parameters;
    }

    private static function query(PDO $connection, string $sql): \PDOStatement
    {
        $statement = $connection->query($sql);
        if (!$statement instanceof \PDOStatement) {
            throw new \RuntimeException('Security audit query failed.');
        }

        return $statement;
    }

    private static function count(PDO $connection, string $sql, string $context): int
    {
        return DatabaseResult::integer(self::query($connection, $sql)->fetchColumn(), $context);
    }
}
