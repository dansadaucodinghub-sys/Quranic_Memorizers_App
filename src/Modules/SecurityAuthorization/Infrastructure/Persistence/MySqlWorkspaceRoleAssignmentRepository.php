<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Infrastructure\Persistence;

use PDO;
use Qmdb\Modules\SecurityAuthorization\Domain\Repository\WorkspaceRoleAssignmentRepository;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleAssignmentActorKind;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleAssignmentReasonCode;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleAssignmentStatus;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleAssignmentVersion;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleCode;
use Qmdb\Modules\SecurityAuthorization\Domain\WorkspaceRoleAssignment;
use Qmdb\Modules\SecurityAuthorization\Domain\WorkspaceRoleAssignmentId;
use Qmdb\Modules\Tenancy\Application\TenantContext;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;

final readonly class MySqlWorkspaceRoleAssignmentRepository implements WorkspaceRoleAssignmentRepository
{
    private const string SELECT = 'SELECT assignment.id, assignment.public_id, assignment.workspace_id, '
        . 'assignment.membership_id, membership.user_account_id AS account_id, assignment.role_id, '
        . 'role_definition.code AS role_code, assignment.status, assignment.version, assignment.assigned_by_kind, '
        . 'assignment.assigned_by_account_id, assignment.assignment_reason_code, assignment.assigned_at, '
        . 'assignment.revoked_by_kind, assignment.revoked_by_account_id, assignment.revocation_reason_code, '
        . 'assignment.revoked_at, assignment.correlation_id, assignment.created_at, assignment.updated_at '
        . 'FROM workspace_role_assignments assignment '
        . 'INNER JOIN workspace_memberships membership ON membership.workspace_id = assignment.workspace_id '
        . 'AND membership.id = assignment.membership_id '
        . 'INNER JOIN authorization_roles role_definition ON role_definition.id = assignment.role_id '
        . "AND role_definition.scope_type = 'WORKSPACE' ";

    public function __construct(private DatabaseConnectionProvider $provider)
    {
    }

    public function add(TenantContext $context, WorkspaceRoleAssignment $assignment): int
    {
        if ($context->workspaceInternalId() !== $assignment->workspaceInternalId) {
            throw new \DomainException('Workspace assignment scope does not match trusted tenant context.');
        }
        $statement = $this->provider->connection()->prepare(
            'INSERT INTO workspace_role_assignments '
            . '(public_id, workspace_id, membership_id, role_id, role_scope_type, status, version, '
            . 'assigned_by_kind, assigned_by_account_id, assignment_reason_code, assigned_at, correlation_id, '
            . 'created_at, updated_at) VALUES '
            . "(:public_id, :workspace_id, :membership_id, :role_id, 'WORKSPACE', 'ACTIVE', :version, "
            . ':assigned_by_kind, :assigned_by_account_id, :assignment_reason, :assigned_at, :correlation_id, '
            . ':created_at, :updated_at)',
        );
        $statement->bindValue(':public_id', $assignment->id->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':workspace_id', $context->workspaceInternalId(), PDO::PARAM_INT);
        $statement->bindValue(':membership_id', $assignment->membershipInternalId, PDO::PARAM_INT);
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
        TenantContext $context,
        WorkspaceRoleAssignmentId $assignmentId,
        bool $forUpdate = false,
    ): ?WorkspaceRoleAssignment {
        $statement = $this->provider->connection()->prepare(
            self::SELECT . "WHERE assignment.workspace_id = :workspace_id "
            . "AND assignment.public_id = :public_id AND assignment.status = 'ACTIVE' LIMIT 1"
            . ($forUpdate ? ' FOR UPDATE' : ''),
        );
        $statement->bindValue(':workspace_id', $context->workspaceInternalId(), PDO::PARAM_INT);
        $statement->bindValue(':public_id', $assignmentId->toBinary(), PDO::PARAM_LOB);
        $statement->execute();

        return $this->one($statement->fetch(PDO::FETCH_ASSOC));
    }

    public function findActiveForMembershipAndRole(
        TenantContext $context,
        int $membershipInternalId,
        int $roleInternalId,
        bool $forUpdate = false,
    ): ?WorkspaceRoleAssignment {
        $statement = $this->provider->connection()->prepare(
            self::SELECT . 'WHERE assignment.workspace_id = :workspace_id '
            . 'AND assignment.membership_id = :membership_id AND assignment.role_id = :role_id '
            . "AND assignment.status = 'ACTIVE' LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : ''),
        );
        $statement->execute([
            ':workspace_id' => $context->workspaceInternalId(),
            ':membership_id' => $membershipInternalId,
            ':role_id' => $roleInternalId,
        ]);

        return $this->one($statement->fetch(PDO::FETCH_ASSOC));
    }

    public function listActiveForMembership(
        TenantContext $context,
        int $membershipInternalId,
        int $limit = 50,
        bool $forUpdate = false,
    ): array {
        return $this->list(
            self::SELECT . 'WHERE assignment.workspace_id = :workspace_id '
            . "AND assignment.membership_id = :membership_id AND assignment.status = 'ACTIVE' "
            . 'ORDER BY assignment.id LIMIT :row_limit' . ($forUpdate ? ' FOR UPDATE' : ''),
            [
                ':workspace_id' => $context->workspaceInternalId(),
                ':membership_id' => $membershipInternalId,
            ],
            $limit,
        );
    }

    public function listActiveForRole(TenantContext $context, int $roleInternalId, int $limit = 100): array
    {
        return $this->list(
            self::SELECT . "WHERE assignment.workspace_id = :workspace_id AND assignment.role_id = :role_id "
            . "AND assignment.status = 'ACTIVE' ORDER BY assignment.id LIMIT :row_limit",
            [':workspace_id' => $context->workspaceInternalId(), ':role_id' => $roleInternalId],
            $limit,
        );
    }

    public function revoke(TenantContext $context, WorkspaceRoleAssignment $assignment): bool
    {
        if (
            $context->workspaceInternalId() !== $assignment->workspaceInternalId
            || $assignment->status !== RoleAssignmentStatus::REVOKED
            || $assignment->internalId === null
        ) {
            throw new \InvalidArgumentException('Repository revocation requires an exact-scope revoked assignment.');
        }
        $statement = $this->provider->connection()->prepare(
            "UPDATE workspace_role_assignments SET status = 'REVOKED', version = :new_version, "
            . 'revoked_by_kind = :revoked_by_kind, revoked_by_account_id = :revoked_by_account_id, '
            . 'revocation_reason_code = :revocation_reason, revoked_at = :revoked_at, updated_at = :updated_at '
            . 'WHERE id = :id AND workspace_id = :workspace_id AND membership_id = :membership_id '
            . "AND status = 'ACTIVE' AND version = :expected_version",
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
            ':workspace_id' => $context->workspaceInternalId(),
            ':membership_id' => $assignment->membershipInternalId,
            ':expected_version' => $assignment->version->value - 1,
        ]);

        return $statement->rowCount() === 1;
    }

    public function countActiveWorkspaceOwners(TenantContext $context, bool $forUpdate = false): int
    {
        $statement = $this->provider->connection()->prepare(
            'SELECT assignment.id FROM workspace_role_assignments assignment '
            . 'INNER JOIN authorization_roles role_definition ON role_definition.id = assignment.role_id '
            . "AND role_definition.code = 'workspace.owner' AND role_definition.scope_type = 'WORKSPACE' "
            . "AND role_definition.status = 'ACTIVE' "
            . 'INNER JOIN workspace_memberships membership ON membership.id = assignment.membership_id '
            . "AND membership.workspace_id = assignment.workspace_id AND membership.status_code = 'ACTIVE' "
            . 'INNER JOIN user_accounts account ON account.id = membership.user_account_id '
            . "AND account.account_status = 'ACTIVE' WHERE assignment.workspace_id = :workspace_id "
            . "AND assignment.status = 'ACTIVE' ORDER BY assignment.id" . ($forUpdate ? ' FOR UPDATE' : ''),
        );
        $statement->execute([':workspace_id' => $context->workspaceInternalId()]);

        return count($statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * @param array<string, int> $parameters
     * @return list<WorkspaceRoleAssignment>
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
                throw new \UnexpectedValueException('Workspace role assignment row is invalid.');
            }
            $assignments[] = $assignment;
        }

        return $assignments;
    }

    private function one(mixed $value): ?WorkspaceRoleAssignment
    {
        $row = AuthorizationPersistence::row($value);
        if ($row === null) {
            return null;
        }

        return new WorkspaceRoleAssignment(
            AuthorizationPersistence::integer($row, 'id'),
            WorkspaceRoleAssignmentId::fromBinary(AuthorizationPersistence::string($row, 'public_id')),
            AuthorizationPersistence::integer($row, 'workspace_id'),
            AuthorizationPersistence::integer($row, 'membership_id'),
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
