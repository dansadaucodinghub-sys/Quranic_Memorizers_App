<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccountState\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Qmdb\Modules\IdentityAccountState\Application\AccountStateOperationCommand;
use Qmdb\Modules\IdentityAccountState\Application\AccountStateOperationResult;
use Qmdb\Modules\IdentityAccountState\Application\AccountStateRepository;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Database\Result\DatabaseResult;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class MySqlAccountStateRepository implements AccountStateRepository
{
    public function __construct(private DatabaseConnectionProvider $provider)
    {
    }

    public function lockAccount(UuidV7 $publicId): ?array
    {
        $statement = $this->provider->connection()->prepare(
            'SELECT id, public_id, account_status, version FROM user_accounts WHERE public_id = :public_id FOR UPDATE',
        );
        $statement->bindValue(':public_id', $publicId->toBinary(), PDO::PARAM_LOB);
        $statement->execute();
        $row = DatabaseResult::nullableRow($statement->fetch(PDO::FETCH_ASSOC), 'Account-state account');
        if ($row === null) {
            return null;
        }

        return [
            'id' => DatabaseResult::integer($row['id'] ?? null, 'Account ID'),
            'public_id' => DatabaseResult::string($row['public_id'] ?? null, 'Account public ID'),
            'account_status' => DatabaseResult::string($row['account_status'] ?? null, 'Account status'),
            'version' => DatabaseResult::integer($row['version'] ?? null, 'Account version'),
        ];
    }

    public function anotherUsableSecurityAdministratorExists(int $targetAccountId): bool
    {
        $lock = $this->provider->connection()->prepare(
            'SELECT assignment.id FROM platform_role_assignments assignment '
            . 'INNER JOIN authorization_roles role_definition ON role_definition.id = assignment.role_id '
            . "WHERE role_definition.code = 'platform.security_administrator' AND assignment.status = 'ACTIVE' FOR UPDATE",
        );
        $lock->execute();
        $statement = $this->provider->connection()->prepare(
            'SELECT COUNT(*) FROM platform_role_assignments assignment '
            . 'INNER JOIN authorization_roles role_definition ON role_definition.id = assignment.role_id '
            . 'INNER JOIN user_accounts account ON account.id = assignment.account_id '
            . "WHERE role_definition.code = 'platform.security_administrator' AND role_definition.status = 'ACTIVE' "
            . "AND assignment.status = 'ACTIVE' AND account.account_status = 'ACTIVE' AND account.id <> :target",
        );
        $statement->execute([':target' => $targetAccountId]);

        return DatabaseResult::integer($statement->fetchColumn(), 'Usable administrator count') > 0;
    }

    public function transition(int $accountId, string $from, string $to, int $expectedVersion, DateTimeImmutable $now): bool
    {
        $statement = $this->provider->connection()->prepare(
            'UPDATE user_accounts SET account_status = :new_status, version = version + 1, updated_at = :updated_at '
            . 'WHERE id = :id AND account_status = :old_status AND version = :version',
        );
        $statement->execute([
            ':new_status' => $to, ':updated_at' => self::timestamp($now), ':id' => $accountId,
            ':old_status' => $from, ':version' => $expectedVersion,
        ]);

        return $statement->rowCount() === 1;
    }

    public function appendStatusEvent(int $accountId, string $eventType, DateTimeImmutable $now): void
    {
        $timestamp = self::timestamp($now);
        $statement = $this->provider->connection()->prepare(
            'INSERT INTO account_status_events (user_account_id, event_type, occurred_at, payload_json, content_hash) '
            . 'VALUES (:account_id, :event_type, :occurred_at, NULL, :hash)',
        );
        $statement->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $statement->bindValue(':event_type', $eventType);
        $statement->bindValue(':occurred_at', $timestamp);
        $statement->bindValue(':hash', hash('sha256', $accountId . "\0" . $eventType . "\0" . $timestamp, true), PDO::PARAM_LOB);
        $statement->execute();
    }

    public function revokeActiveAccess(int $accountId, DateTimeImmutable $now): void
    {
        $timestamp = self::timestamp($now);
        $connection = $this->provider->connection();
        $updates = [
            "UPDATE user_sessions SET status = 'REVOKED', revoked_at = :revoked_at, revoke_reason_code = 'ACCOUNT_NOT_ACTIVE', version = version + 1, updated_at = :updated_at WHERE account_id = :account_id AND status = 'ACTIVE'",
            "UPDATE account_authentication_transactions SET status = 'REVOKED', revoked_at = :revoked_at, version = version + 1, updated_at = :updated_at WHERE account_id = :account_id AND status = 'PENDING'",
            "UPDATE account_step_up_grants SET status = 'REVOKED', revoked_at = :revoked_at, version = version + 1, updated_at = :updated_at WHERE account_id = :account_id AND status = 'ACTIVE'",
            "UPDATE account_webauthn_ceremonies SET status = 'REVOKED', revoked_at = :revoked_at, version = version + 1, updated_at = :updated_at WHERE account_id = :account_id AND status = 'PENDING'",
            "UPDATE account_password_recovery_challenges SET status = 'REVOKED', revoked_at = :revoked_at, version = version + 1, updated_at = :updated_at WHERE account_id = :account_id AND status = 'PENDING'",
            "UPDATE privileged_access_activations SET status = 'REVOKED', revoked_at = :revoked_at, revoke_reason_code = 'ACCOUNT_SUSPENDED', version = version + 1, updated_at = :updated_at WHERE subject_account_id = :account_id AND status = 'ACTIVE'",
            "UPDATE privileged_access_requests SET status = 'REVOKED', revoked_at = :revoked_at, version = version + 1, updated_at = :updated_at WHERE subject_account_id = :account_id AND status IN ('REQUESTED','PARTIALLY_APPROVED','APPROVED')",
        ];
        foreach ($updates as $sql) {
            $statement = $connection->prepare($sql);
            $statement->execute([
                ':revoked_at' => $timestamp,
                ':updated_at' => $timestamp,
                ':account_id' => $accountId,
            ]);
        }
    }

    public function findCompletedSubmission(UuidV7 $submissionId, string $fingerprint): ?AccountStateOperationResult
    {
        $statement = $this->provider->connection()->prepare(
            'SELECT operation.public_id, operation.request_fingerprint, account.public_id AS account_public_id, operation.target_account_version_after '
            . 'FROM account_state_operations operation INNER JOIN user_accounts account ON account.id = operation.target_account_id '
            . 'WHERE operation.submission_id = :submission_id FOR UPDATE',
        );
        $statement->bindValue(':submission_id', $submissionId->toBinary(), PDO::PARAM_LOB);
        $statement->execute();
        $row = DatabaseResult::nullableRow($statement->fetch(PDO::FETCH_ASSOC), 'Account-state submission');
        if ($row === null) {
            return null;
        }
        $storedFingerprint = $row['request_fingerprint'] ?? null;
        if (!is_string($storedFingerprint) || !hash_equals($storedFingerprint, $fingerprint)) {
            throw new \DomainException('Account-state submission identifier conflicts with a different request.');
        }

        return new AccountStateOperationResult(
            UuidV7::fromBinary(DatabaseResult::string($row['public_id'] ?? null, 'Operation public ID'))->toString(),
            UuidV7::fromBinary(DatabaseResult::string($row['account_public_id'] ?? null, 'Target account public ID'))->toString(),
            DatabaseResult::integer($row['target_account_version_after'] ?? null, 'Target account version'),
        );
    }

    public function record(UuidV7 $operationId, AccountStateOperationCommand $command, int $targetInternalId, int $stepUpGrantId, string $auditEventPublicId, int $versionBefore, DateTimeImmutable $now): void
    {
        $fingerprint = hash('sha256', implode("\0", [
            $command->operation->value, $command->targetAccountId->toString(), $command->actor->accountId->toString(),
            $command->reason->value, hash('sha256', $command->justification->value), $command->reference->value ?? '',
        ]), true);
        $statement = $this->provider->connection()->prepare(
            'INSERT INTO account_state_operations (public_id, submission_id, request_fingerprint, operation_type, '
            . 'target_account_id, actor_account_id, previous_status, new_status, reason_code, justification, reference_code, '
            . 'step_up_grant_id, audit_event_public_id, target_account_version_before, target_account_version_after, '
            . 'correlation_id, occurred_at, created_at) VALUES (:public_id, :submission_id, :fingerprint, :operation, '
            . ':target, :actor, :previous, :new, :reason, :justification, :reference, :step_up, :audit_event, :before, :after, '
            . ':correlation, :occurred_at, :created_at)',
        );
        $statement->bindValue(':public_id', $operationId->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':submission_id', $command->submissionId->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':fingerprint', $fingerprint, PDO::PARAM_LOB);
        $statement->bindValue(':operation', $command->operation->value);
        $statement->bindValue(':target', $targetInternalId, PDO::PARAM_INT);
        $statement->bindValue(':actor', $command->actor->accountInternalId, PDO::PARAM_INT);
        $statement->bindValue(':previous', $command->operation->value === 'SUSPEND' ? 'ACTIVE' : 'SUSPENDED');
        $statement->bindValue(':new', $command->operation->value === 'SUSPEND' ? 'SUSPENDED' : 'ACTIVE');
        $statement->bindValue(':reason', $command->reason->value);
        $statement->bindValue(':justification', $command->justification->value);
        $statement->bindValue(':reference', $command->reference->value);
        $statement->bindValue(':step_up', $stepUpGrantId, PDO::PARAM_INT);
        $statement->bindValue(':audit_event', UuidV7::fromString($auditEventPublicId)->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':before', $versionBefore, PDO::PARAM_INT);
        $statement->bindValue(':after', $versionBefore + 1, PDO::PARAM_INT);
        $statement->bindValue(':correlation', $command->correlationId === null ? null : hex2bin($command->correlationId), $command->correlationId === null ? PDO::PARAM_NULL : PDO::PARAM_LOB);
        $statement->bindValue(':occurred_at', self::timestamp($now));
        $statement->bindValue(':created_at', self::timestamp($now));
        $statement->execute();
    }

    private static function timestamp(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }
}
