<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Infrastructure\Persistence;

use PDO;
use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\SecurityAuthorization\Domain\PlatformRoleAssignment;
use Qmdb\Modules\SecurityAuthorization\Domain\PlatformRoleAssignmentId;
use Qmdb\Modules\SecurityAuthorization\Domain\Repository\PlatformRoleAssignmentRepository;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleAssignmentActorKind;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleAssignmentReasonCode;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleAssignmentStatus;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleAssignmentVersion;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleCode;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;

final readonly class MySqlPlatformRoleAssignmentRepository implements PlatformRoleAssignmentRepository
{
    private const string SELECT = 'SELECT assignment.id, assignment.public_id, assignment.account_id, '
        . 'assignment.role_id, role_definition.code AS role_code, assignment.status, assignment.version, '
        . 'assignment.assigned_by_kind, assignment.assigned_by_account_id, assignment.assignment_reason_code, '
        . 'assignment.assigned_at, assignment.revoked_by_kind, assignment.revoked_by_account_id, '
        . 'assignment.revocation_reason_code, assignment.revoked_at, assignment.correlation_id, '
        . 'assignment.created_at, assignment.updated_at FROM platform_role_assignments assignment '
        . 'INNER JOIN authorization_roles role_definition ON role_definition.id = assignment.role_id '
        . "AND role_definition.scope_type = 'PLATFORM' ";

    public function __construct(private DatabaseConnectionProvider $provider)
    {
    }

    public function add(PlatformRoleAssignment $assignment): int
    {
        $statement = $this->provider->connection()->prepare(
            'INSERT INTO platform_role_assignments '
            . '(public_id, account_id, role_id, role_scope_type, status, version, assigned_by_kind, '
            . 'assigned_by_account_id, assignment_reason_code, assigned_at, correlation_id, created_at, updated_at) '
            . "VALUES (:public_id, :account_id, :role_id, 'PLATFORM', 'ACTIVE', :version, :assigned_by_kind, "
            . ':assigned_by_account_id, :assignment_reason, :assigned_at, :correlation_id, :created_at, :updated_at)',
        );
        $statement->bindValue(':public_id', $assignment->id->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':account_id', $assignment->accountInternalId, PDO::PARAM_INT);
        $statement->bindValue(':role_id', $assignment->roleInternalId, PDO::PARAM_INT);
        $statement->bindValue(':version', $assignment->version->value, PDO::PARAM_INT);
        $statement->bindValue(':assigned_by_kind', $assignment->assignedByKind->value);
        $statement->bindValue(':assigned_by_account_id', $assignment->assignedByAccountInternalId, PDO::PARAM_INT);
        $statement->bindValue(':assignment_reason', $assignment->assignmentReason->value);
        $statement->bindValue(':assigned_at', AuthorizationPersistence::format($assignment->assignedAt));
        $statement->bindValue(':correlation_id', self::correlationBinary($assignment->correlationId), PDO::PARAM_LOB);
        $statement->bindValue(':created_at', AuthorizationPersistence::format($assignment->createdAt));
        $statement->bindValue(':updated_at', AuthorizationPersistence::format($assignment->updatedAt));
        $statement->execute();

        return (int) $this->provider->connection()->lastInsertId();
    }

    public function findActiveAssignment(
        AccountId $accountId,
        PlatformRoleAssignmentId $assignmentId,
        bool $forUpdate = false,
    ): ?PlatformRoleAssignment {
        $statement = $this->provider->connection()->prepare(
            self::SELECT . 'INNER JOIN user_accounts account ON account.id = assignment.account_id '
            . "WHERE account.public_id = :account_public_id AND assignment.public_id = :assignment_public_id "
            . "AND assignment.status = 'ACTIVE' LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : ''),
        );
        $statement->bindValue(':account_public_id', $accountId->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':assignment_public_id', $assignmentId->toBinary(), PDO::PARAM_LOB);
        $statement->execute();

        return $this->one($statement->fetch(PDO::FETCH_ASSOC));
    }

    public function findActiveForAccountAndRole(
        int $accountInternalId,
        int $roleInternalId,
        bool $forUpdate = false,
    ): ?PlatformRoleAssignment {
        $statement = $this->provider->connection()->prepare(
            self::SELECT . "WHERE assignment.account_id = :account_id AND assignment.role_id = :role_id "
            . "AND assignment.status = 'ACTIVE' LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : ''),
        );
        $statement->execute([':account_id' => $accountInternalId, ':role_id' => $roleInternalId]);

        return $this->one($statement->fetch(PDO::FETCH_ASSOC));
    }

    public function listActiveForAccount(int $accountInternalId, int $limit = 50, bool $forUpdate = false): array
    {
        return $this->list(
            self::SELECT . "WHERE assignment.account_id = :account_id AND assignment.status = 'ACTIVE' "
            . 'ORDER BY assignment.id LIMIT :row_limit' . ($forUpdate ? ' FOR UPDATE' : ''),
            [':account_id' => $accountInternalId],
            $limit,
        );
    }

    public function listActiveForRole(int $roleInternalId, int $limit = 100): array
    {
        return $this->list(
            self::SELECT . "WHERE assignment.role_id = :role_id AND assignment.status = 'ACTIVE' "
            . 'ORDER BY assignment.id LIMIT :row_limit',
            [':role_id' => $roleInternalId],
            $limit,
        );
    }

    public function revoke(PlatformRoleAssignment $assignment): bool
    {
        if ($assignment->status !== RoleAssignmentStatus::REVOKED || $assignment->internalId === null) {
            throw new \InvalidArgumentException('Repository revocation requires a revoked persisted assignment.');
        }
        $statement = $this->provider->connection()->prepare(
            "UPDATE platform_role_assignments SET status = 'REVOKED', version = :new_version, "
            . 'revoked_by_kind = :revoked_by_kind, revoked_by_account_id = :revoked_by_account_id, '
            . 'revocation_reason_code = :revocation_reason, revoked_at = :revoked_at, updated_at = :updated_at '
            . "WHERE id = :id AND account_id = :account_id AND status = 'ACTIVE' AND version = :expected_version",
        );
        $statement->execute([
            ':new_version' => $assignment->version->value,
            ':revoked_by_kind' => $assignment->revokedByKind?->value,
            ':revoked_by_account_id' => $assignment->revokedByAccountInternalId,
            ':revocation_reason' => $assignment->revocationReason?->value,
            ':revoked_at' => $assignment->revokedAt === null
                ? null : AuthorizationPersistence::format($assignment->revokedAt),
            ':updated_at' => AuthorizationPersistence::format($assignment->updatedAt),
            ':id' => $assignment->internalId,
            ':account_id' => $assignment->accountInternalId,
            ':expected_version' => $assignment->version->value - 1,
        ]);

        return $statement->rowCount() === 1;
    }

    public function countActivePlatformSecurityAdministrators(bool $forUpdate = false): int
    {
        $sql = 'SELECT assignment.id FROM platform_role_assignments assignment '
            . 'INNER JOIN authorization_roles role_definition ON role_definition.id = assignment.role_id '
            . "AND role_definition.code = 'platform.security_administrator' "
            . "AND role_definition.scope_type = 'PLATFORM' AND role_definition.status = 'ACTIVE' "
            . 'INNER JOIN user_accounts account ON account.id = assignment.account_id '
            . "AND account.account_status = 'ACTIVE' WHERE assignment.status = 'ACTIVE' "
            . 'ORDER BY assignment.id' . ($forUpdate ? ' FOR UPDATE' : '');
        $statement = $this->provider->connection()->prepare($sql);
        $statement->execute();

        return count($statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * @param array<string, int> $parameters
     * @return list<PlatformRoleAssignment>
     */
    private function list(string $sql, array $parameters, int $limit): array
    {
        $statement = $this->provider->connection()->prepare($sql);
        foreach ($parameters as $name => $value) {
            $statement->bindValue($name, $value, PDO::PARAM_INT);
        }
        $statement->bindValue(':row_limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $statement->execute();
        $assignments = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $assignment = $this->one($row);
            if ($assignment === null) {
                throw new \UnexpectedValueException('Platform role assignment row is invalid.');
            }
            $assignments[] = $assignment;
        }

        return $assignments;
    }

    private function one(mixed $value): ?PlatformRoleAssignment
    {
        $row = AuthorizationPersistence::row($value);
        if ($row === null) {
            return null;
        }

        return new PlatformRoleAssignment(
            AuthorizationPersistence::integer($row, 'id'),
            PlatformRoleAssignmentId::fromBinary(AuthorizationPersistence::string($row, 'public_id')),
            AuthorizationPersistence::integer($row, 'account_id'),
            AuthorizationPersistence::integer($row, 'role_id'),
            new RoleCode(AuthorizationPersistence::string($row, 'role_code')),
            RoleAssignmentStatus::from(AuthorizationPersistence::string($row, 'status')),
            new RoleAssignmentVersion(AuthorizationPersistence::integer($row, 'version')),
            RoleAssignmentActorKind::from(AuthorizationPersistence::string($row, 'assigned_by_kind')),
            AuthorizationPersistence::nullableInteger($row, 'assigned_by_account_id'),
            RoleAssignmentReasonCode::from(AuthorizationPersistence::string($row, 'assignment_reason_code')),
            new \DateTimeImmutable(AuthorizationPersistence::string($row, 'assigned_at')),
            ($kind = AuthorizationPersistence::nullableString($row, 'revoked_by_kind')) === null
                ? null : RoleAssignmentActorKind::from($kind),
            AuthorizationPersistence::nullableInteger($row, 'revoked_by_account_id'),
            ($reason = AuthorizationPersistence::nullableString($row, 'revocation_reason_code')) === null
                ? null : RoleAssignmentReasonCode::from($reason),
            ($revokedAt = AuthorizationPersistence::nullableString($row, 'revoked_at')) === null
                ? null : new \DateTimeImmutable($revokedAt),
            bin2hex(AuthorizationPersistence::string($row, 'correlation_id')),
            new \DateTimeImmutable(AuthorizationPersistence::string($row, 'created_at')),
            new \DateTimeImmutable(AuthorizationPersistence::string($row, 'updated_at')),
        );
    }

    private static function correlationBinary(string $value): string
    {
        $binary = hex2bin($value);
        if (!is_string($binary) || strlen($binary) !== 16) {
            throw new \InvalidArgumentException('Correlation ID is invalid.');
        }

        return $binary;
    }
}
