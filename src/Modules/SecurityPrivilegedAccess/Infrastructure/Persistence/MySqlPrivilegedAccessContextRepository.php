<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace Qmdb\Modules\SecurityPrivilegedAccess\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationScopeType;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessContextRepository;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessActivationId;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessContext;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessRequestId;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessType;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedWorkspaceTenantContext;
use Qmdb\Modules\Tenancy\Domain\Value\WorkspaceId;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class MySqlPrivilegedAccessContextRepository implements PrivilegedAccessContextRepository
{
    public function __construct(private DatabaseConnectionProvider $provider)
    {
    }

    public function activeContext(AuthenticatedAccountContext $actor, DateTimeImmutable $now): ?array
    {
        $pdo = $this->provider->connection();
        if (!$this->schemaIsAvailable($pdo)) {
            return null;
        }
        $statement = $pdo->prepare(<<<'SQL'
SELECT activation.public_id AS activation_public_id, activation.access_type, activation.scope_type,
       activation.workspace_id, activation.assurance_level, activation.activated_at, activation.expires_at,
       activation.tenant_context_version_at_activation, request_record.public_id AS request_public_id,
       workspace.public_id AS workspace_public_id, workspace.name AS workspace_name
FROM privileged_access_activations activation
INNER JOIN privileged_access_requests request_record ON request_record.id = activation.request_id
INNER JOIN user_accounts account ON account.id = activation.subject_account_id
INNER JOIN user_sessions session_record ON session_record.id = activation.session_id
    AND session_record.account_id = activation.subject_account_id
LEFT JOIN workspaces workspace ON workspace.id = activation.workspace_id
LEFT JOIN workspace_memberships membership ON membership.id = request_record.subject_membership_id
WHERE activation.subject_account_id = :account_id
  AND activation.session_id = :session_id
  AND activation.status = 'ACTIVE'
  AND activation.expires_at > :now_activation
  AND request_record.status = 'ACTIVE'
  AND account.account_status = 'ACTIVE'
  AND session_record.status = 'ACTIVE'
  AND session_record.idle_expires_at > :now_idle
  AND session_record.absolute_expires_at > :now_absolute
  AND (
      activation.scope_type = 'PLATFORM'
      OR (workspace.status_code = 'ACTIVE'
          AND (request_record.subject_membership_id IS NULL OR membership.status_code = 'ACTIVE'))
  )
ORDER BY activation.id DESC
LIMIT 1
SQL);
        $timestamp = $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
        $statement->execute([
            ':account_id' => $actor->accountInternalId,
            ':session_id' => $actor->sessionInternalId,
            ':now_activation' => $timestamp,
            ':now_idle' => $timestamp,
            ':now_absolute' => $timestamp,
        ]);
        $row = self::row($statement->fetch(PDO::FETCH_ASSOC));
        if ($row === null) {
            return null;
        }
        $scope = AuthorizationScopeType::from(self::string($row, 'scope_type'));
        $workspaceInternalId = self::nullableInteger($row, 'workspace_id');
        $context = new PrivilegedAccessContext(
            $actor->accountInternalId,
            $actor->sessionInternalId,
            PrivilegedAccessRequestId::fromBinary(self::string($row, 'request_public_id')),
            PrivilegedAccessActivationId::fromBinary(self::string($row, 'activation_public_id')),
            PrivilegedAccessType::from(self::string($row, 'access_type')),
            $scope,
            $workspaceInternalId,
            self::integer($row, 'tenant_context_version_at_activation'),
            AuthenticationAssuranceLevel::from(self::string($row, 'assurance_level')),
            self::dateTime($row, 'activated_at'),
            self::dateTime($row, 'expires_at'),
        );
        if ($scope === AuthorizationScopeType::PLATFORM) {
            return ['context' => $context, 'workspace' => null];
        }
        $workspacePublicId = self::nullableString($row, 'workspace_public_id');
        $workspaceName = self::nullableString($row, 'workspace_name');
        if ($workspacePublicId === null || $workspaceName === null) {
            return null;
        }

        return [
            'context' => $context,
            'workspace' => new PrivilegedWorkspaceTenantContext($context, WorkspaceId::fromBinary($workspacePublicId), $workspaceName),
        ];
    }

    public function invalidateConflictingTenantContext(AuthenticatedAccountContext $actor, DateTimeImmutable $now): void
    {
        $pdo = $this->provider->connection();
        $lock = $pdo->prepare(
            "SELECT activation.id AS activation_id, request_record.id AS request_id FROM privileged_access_activations activation "
            . "INNER JOIN privileged_access_requests request_record ON request_record.id = activation.request_id "
            . "WHERE activation.subject_account_id = :account_id AND activation.session_id = :session_id "
            . "AND activation.status = 'ACTIVE' AND request_record.status = 'ACTIVE' LIMIT 1 FOR UPDATE",
        );
        $lock->execute([':account_id' => $actor->accountInternalId, ':session_id' => $actor->sessionInternalId]);
        $row = self::row($lock->fetch(PDO::FETCH_ASSOC));
        if ($row === null) {
            return;
        }
        $timestamp = $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
        $activationId = self::integer($row, 'activation_id');
        $requestId = self::integer($row, 'request_id');
        $activation = $pdo->prepare(
            "UPDATE privileged_access_activations SET status = 'REVOKED', revoked_at = :now, "
            . "revoke_reason_code = 'CONTEXT_CONFLICT', version = version + 1, updated_at = :now "
            . "WHERE id = :id AND status = 'ACTIVE'",
        );
        $activation->execute([':now' => $timestamp, ':id' => $activationId]);
        if ($activation->rowCount() !== 1) {
            throw new \DomainException('The conflicting privileged activation changed before it could be invalidated.');
        }
        $request = $pdo->prepare(
            "UPDATE privileged_access_requests SET status = 'REVOKED', revoked_at = :now, version = version + 1, "
            . "updated_at = :now WHERE id = :id AND status = 'ACTIVE'",
        );
        $request->execute([':now' => $timestamp, ':id' => $requestId]);
        if ($request->rowCount() !== 1) {
            throw new \DomainException('The conflicting privileged request changed before it could be invalidated.');
        }
        $event = $pdo->prepare(<<<'SQL'
INSERT INTO privileged_access_events
    (public_id, request_id, activation_id, event_type, actor_kind, actor_account_id, reason_code, correlation_id, occurred_at)
VALUES (:public_id, :request_id, :activation_id, 'REVOKED', 'SYSTEM', NULL, 'ACCESS_REVOKED', :correlation_id, :occurred_at)
SQL);
        $event->bindValue(':public_id', UuidV7::generate()->toBinary(), PDO::PARAM_LOB);
        $event->bindValue(':request_id', $requestId, PDO::PARAM_INT);
        $event->bindValue(':activation_id', $activationId, PDO::PARAM_INT);
        $event->bindValue(':correlation_id', UuidV7::generate()->toBinary(), PDO::PARAM_LOB);
        $event->bindValue(':occurred_at', $timestamp);
        $event->execute();
    }

    /** @return array<string, mixed>|null */
    private static function row(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }
        $row = [];
        foreach ($value as $column => $field) {
            if (!is_string($column)) {
                throw new \UnexpectedValueException('Privileged-access context row is invalid.');
            }
            $row[$column] = $field;
        }

        return $row;
    }

    /** @param array<string, mixed> $row */
    private static function string(array $row, string $column): string
    {
        $value = $row[$column] ?? null;
        if (!is_string($value)) {
            throw new \UnexpectedValueException('Privileged-access context row is invalid.');
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    private static function nullableString(array $row, string $column): ?string
    {
        $value = $row[$column] ?? null;
        if ($value !== null && !is_string($value)) {
            throw new \UnexpectedValueException('Privileged-access context row is invalid.');
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    private static function integer(array $row, string $column): int
    {
        $value = $row[$column] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value) || !ctype_digit($value)) {
            throw new \UnexpectedValueException('Privileged-access context row is invalid.');
        }

        return (int) $value;
    }

    /** @param array<string, mixed> $row */
    private static function nullableInteger(array $row, string $column): ?int
    {
        return ($row[$column] ?? null) === null ? null : self::integer($row, $column);
    }

    /** @param array<string, mixed> $row */
    private static function dateTime(array $row, string $column): DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s.u', self::string($row, $column), new DateTimeZone('UTC'));
        if (!$parsed instanceof DateTimeImmutable) {
            throw new \UnexpectedValueException('Privileged-access context timestamp is invalid.');
        }

        return $parsed;
    }

    private function schemaIsAvailable(PDO $pdo): bool
    {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() '
            . "AND table_name IN ('privileged_access_requests', 'privileged_access_activations')",
        );
        $statement->execute();
        $count = $statement->fetchColumn();

        return (is_int($count) || is_string($count) && ctype_digit($count)) && (int) $count === 2;
    }
}
