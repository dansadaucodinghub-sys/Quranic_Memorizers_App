<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOStatement;
use Qmdb\Modules\Community\Application\CommunityGlobalReceipts;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class MySqlCommunityGlobalReceipts implements CommunityGlobalReceipts
{
    public function __construct(private DatabaseConnectionProvider $connections)
    {
    }

    public function completedForActor(
        UuidV7 $submission,
        int $actorId,
        string $operation,
        string $fingerprint
    ): ?array {
        $this->requireTransaction();
        $row = $this->query(
            'SELECT actor_account_id,operation_code,request_fingerprint,result_public_id,result_status,result_version,completed_at FROM community_global_operation_receipts WHERE submission_public_id=:submission FOR UPDATE',
            ['submission' => $submission->toBinary()]
        )->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        if (
            !is_array($row) || !is_string($row['operation_code'] ?? null)
            || !is_string($row['request_fingerprint'] ?? null)
            || $this->integer($row, 'actor_account_id') !== $actorId
            || $row['operation_code'] !== $operation
            || !hash_equals($row['request_fingerprint'], $fingerprint)
        ) {
            throw new \DomainException('Submission identifier conflicts with another social operation.');
        }
        if ($row['completed_at'] === null) {
            throw new \DomainException('Matching social operation is in progress.');
        }
        if (!is_string($row['result_public_id']) || !is_string($row['result_status'])) {
            throw new \UnexpectedValueException('Completed social receipt is malformed.');
        }
        return ['public_id' => UuidV7::fromBinary($row['result_public_id'])->toString(),
            'status' => $row['result_status'], 'version' => $this->integer($row, 'result_version')];
    }

    public function claim(
        UuidV7 $submission,
        int $actorId,
        string $operation,
        string $fingerprint,
        DateTimeImmutable $now
    ): ?array {
        $this->requireTransaction();
        if (strlen($fingerprint) !== 32 || preg_match('/\A[A-Z][A-Z_]{2,31}\z/', $operation) !== 1) {
            throw new \InvalidArgumentException('Global community receipt metadata is invalid.');
        }
        $insert = $this->query(
            'INSERT IGNORE INTO community_global_operation_receipts (submission_public_id,actor_account_id,operation_code,request_fingerprint,created_at) VALUES (:submission,:actor,:operation,:fingerprint,:created)',
            ['submission' => $submission->toBinary(), 'actor' => $actorId,
            'operation' => $operation,
            'fingerprint' => $fingerprint,
            'created' => $this->time($now)]
        );
        if ($insert->rowCount() === 1) {
            return null;
        }
        $row = $this->query(
            'SELECT actor_account_id,operation_code,request_fingerprint,result_public_id,result_status,result_version,completed_at FROM community_global_operation_receipts WHERE submission_public_id=:submission FOR UPDATE',
            ['submission' => $submission->toBinary()]
        )->fetch(PDO::FETCH_ASSOC);
        if (
            !is_array($row) || !is_string($row['operation_code'] ?? null)
            || !is_string($row['request_fingerprint'] ?? null)
            || $this->integer($row, 'actor_account_id') !== $actorId
            || $row['operation_code'] !== $operation
            || !hash_equals($row['request_fingerprint'], $fingerprint)
        ) {
            throw new \DomainException('Submission identifier conflicts with another social operation.');
        }
        if ($row['completed_at'] === null) {
            throw new \DomainException('Matching social operation is in progress.');
        }
        if (!is_string($row['result_public_id']) || !is_string($row['result_status'])) {
            throw new \UnexpectedValueException('Completed social receipt is malformed.');
        }
        return ['public_id' => UuidV7::fromBinary($row['result_public_id'])->toString(),
            'status' => $row['result_status'], 'version' => $this->integer($row, 'result_version')];
    }

    public function complete(
        UuidV7 $submission,
        UuidV7 $resultId,
        string $status,
        int $version,
        DateTimeImmutable $now
    ): void {
        $this->requireTransaction();
        if ($version < 1 || strlen($status) > 24) {
            throw new \InvalidArgumentException('Global social result is invalid.');
        }
        $updated = $this->query(
            'UPDATE community_global_operation_receipts SET result_public_id=:result,result_status=:status,result_version=:version,completed_at=:completed WHERE submission_public_id=:submission AND completed_at IS NULL',
            ['result' => $resultId->toBinary(), 'status' => $status,
                'version' => $version, 'completed' => $this->time($now),
            'submission' => $submission->toBinary()]
        );
        if ($updated->rowCount() !== 1) {
            throw new \DomainException('Social operation receipt was not claimed.');
        }
    }

    /** @param array<string,int|string|null> $parameters */
    private function query(string $sql, array $parameters): PDOStatement
    {
        $statement = $this->connections->connection()->prepare($sql);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Could not prepare social receipt statement.');
        }
        $statement->execute($parameters);
        return $statement;
    }

    /** @param array<array-key,mixed> $row */
    private function integer(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        return is_int($value) || (is_string($value) && ctype_digit($value))
            ? (int) $value : throw new \UnexpectedValueException('Social receipt is malformed.');
    }

    private function requireTransaction(): void
    {
        if (!$this->connections->connection()->inTransaction()) {
            throw new \LogicException('Social receipts require a transaction.');
        }
    }

    private function time(DateTimeImmutable $now): string
    {
        return $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }
}
