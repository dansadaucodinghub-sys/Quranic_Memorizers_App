<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\IdentitySessions\Domain\SessionStatus;
use Qmdb\Modules\Tenancy\Domain\MembershipStatus;
use Qmdb\Modules\Tenancy\Domain\Value\WorkspaceId;
use Qmdb\Modules\Tenancy\Domain\WorkspaceStatus;
use Qmdb\Modules\TenancyContext\Domain\AccountWorkspaceTenantContext;
use Qmdb\Modules\TenancyContext\Domain\Repository\SessionTenantContextRepository;
use Qmdb\Modules\TenancyContext\Domain\SessionTenantContextState;
use Qmdb\Modules\TenancyContext\Domain\TenantContextVersion;
use Qmdb\Modules\TenancyContext\Domain\WorkspaceContextOption;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;
use UnexpectedValueException;

final readonly class MySqlSessionTenantContextRepository implements SessionTenantContextRepository
{
    public function __construct(private DatabaseConnectionProvider $provider)
    {
    }

    public function state(AuthenticatedAccountContext $account, bool $forUpdate = false): SessionTenantContextState
    {
        $sql = 'SELECT s.tenant_context_version, s.selected_workspace_id, s.selected_membership_id, '
            . 's.status AS session_status, s.version AS session_version, a.account_status, '
            . 'w.public_id AS workspace_public_id, w.name AS workspace_name, w.status_code AS workspace_status, '
            . 'w.version AS workspace_version, m.public_id AS membership_public_id, '
            . 'm.status_code AS membership_status, m.version AS membership_version, '
            . 's.tenant_context_selected_at FROM user_sessions s '
            . 'INNER JOIN user_accounts a ON a.id = s.account_id '
            . 'LEFT JOIN workspaces w ON w.id = s.selected_workspace_id '
            . 'LEFT JOIN workspace_memberships m ON m.id = s.selected_membership_id '
            . 'AND m.workspace_id = s.selected_workspace_id AND m.user_account_id = s.account_id '
            . 'WHERE s.id = :session_id AND s.account_id = :account_id LIMIT 1';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        $statement = $this->pdo()->prepare($sql);
        $statement->execute([
            ':session_id' => $account->sessionInternalId,
            ':account_id' => $account->accountInternalId,
        ]);
        $row = self::row($statement->fetch(PDO::FETCH_ASSOC));
        if ($row === null) {
            throw new UnexpectedValueException('Authenticated session tenant state is unavailable.');
        }
        $version = new TenantContextVersion(self::integer($row, 'tenant_context_version'));
        $workspaceInternalId = self::nullableInteger($row, 'selected_workspace_id');
        $membershipInternalId = self::nullableInteger($row, 'selected_membership_id');
        $stored = $workspaceInternalId !== null || $membershipInternalId !== null;
        $valid = $workspaceInternalId !== null
            && $membershipInternalId !== null
            && self::nullableString($row, 'account_status') === 'ACTIVE'
            && self::nullableString($row, 'session_status') === 'ACTIVE'
            && self::nullableString($row, 'workspace_status') === 'ACTIVE'
            && self::nullableString($row, 'membership_status') === 'ACTIVE'
            && self::nullableString($row, 'workspace_public_id') !== null
            && self::nullableString($row, 'workspace_name') !== null
            && self::nullableString($row, 'membership_public_id') !== null
            && self::nullableString($row, 'tenant_context_selected_at') !== null;

        return new SessionTenantContextState(
            $account->sessionInternalId,
            $account->accountInternalId,
            $workspaceInternalId,
            $membershipInternalId,
            $version,
            self::nullableString($row, 'tenant_context_selected_at') === null
                ? null : self::dateTime($row, 'tenant_context_selected_at'),
            SessionStatus::from(self::string($row, 'session_status')),
            self::integer($row, 'session_version'),
            $stored,
            $valid ? AccountWorkspaceTenantContext::trusted(
                $account->accountInternalId,
                $account->accountId,
                $account->sessionInternalId,
                $account->sessionId,
                $workspaceInternalId,
                WorkspaceId::fromBinary(self::string($row, 'workspace_public_id')),
                WorkspaceStatus::ACTIVE,
                self::integer($row, 'workspace_version'),
                \Qmdb\Modules\TenancyContext\Domain\ResolvedWorkspaceMembershipIdentity::trusted(
                    $membershipInternalId,
                    UuidV7::fromBinary(self::string($row, 'membership_public_id')),
                    $workspaceInternalId,
                    $account->accountInternalId,
                    MembershipStatus::ACTIVE,
                    self::integer($row, 'membership_version'),
                ),
                self::string($row, 'workspace_name'),
                $version,
                self::dateTime($row, 'tenant_context_selected_at'),
            ) : null,
        );
    }

    public function availableForAccount(
        AuthenticatedAccountContext $account,
        int $limit = 50,
        ?WorkspaceId $afterWorkspaceId = null,
    ): array {
        $limit = max(1, min(51, $limit));
        $cursor = $afterWorkspaceId === null ? '' : 'AND w.public_id > :after_workspace_public_id ';
        $statement = $this->pdo()->prepare(
            'SELECT w.id AS workspace_id, w.public_id, w.name, w.status_code AS workspace_status, '
            . 'w.version AS workspace_version, w.updated_at AS workspace_updated_at, '
            . 'm.id AS membership_id, m.public_id AS membership_public_id, '
            . 'm.status_code AS membership_status, m.version AS membership_version '
            . 'FROM workspace_memberships m INNER JOIN workspaces w ON w.id = m.workspace_id '
            . "WHERE m.user_account_id = :account_id AND m.status_code = 'ACTIVE' AND w.status_code = 'ACTIVE' "
            . $cursor . 'ORDER BY w.public_id LIMIT ' . $limit,
        );
        $statement->bindValue(':account_id', $account->accountInternalId, PDO::PARAM_INT);
        if ($afterWorkspaceId !== null) {
            $statement->bindValue(':after_workspace_public_id', $afterWorkspaceId->toBinary(), PDO::PARAM_LOB);
        }
        $statement->execute();
        $options = [];
        while (($row = self::row($statement->fetch(PDO::FETCH_ASSOC))) !== null) {
            $options[] = $this->option($row);
        }

        return $options;
    }

    public function selectable(
        AuthenticatedAccountContext $account,
        WorkspaceId $workspaceId,
        bool $forUpdate = false,
    ): ?WorkspaceContextOption {
        $sql = 'SELECT w.id AS workspace_id, w.public_id, w.name, w.status_code AS workspace_status, '
            . 'w.version AS workspace_version, w.updated_at AS workspace_updated_at, '
            . 'm.id AS membership_id, m.public_id AS membership_public_id, '
            . 'm.status_code AS membership_status, m.version AS membership_version '
            . 'FROM workspace_memberships m INNER JOIN workspaces w ON w.id = m.workspace_id '
            . "WHERE m.user_account_id = :account_id AND m.status_code = 'ACTIVE' "
            . "AND w.public_id = :workspace_public_id AND w.status_code = 'ACTIVE' LIMIT 1";
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        $statement = $this->pdo()->prepare($sql);
        $statement->bindValue(':account_id', $account->accountInternalId, PDO::PARAM_INT);
        $statement->bindValue(':workspace_public_id', $workspaceId->toBinary(), PDO::PARAM_LOB);
        $statement->execute();
        $row = self::row($statement->fetch(PDO::FETCH_ASSOC));

        return $row === null ? null : $this->option($row);
    }

    public function select(
        AuthenticatedAccountContext $account,
        WorkspaceContextOption $workspace,
        TenantContextVersion $expectedVersion,
        DateTimeImmutable $selectedAt,
    ): bool {
        $statement = $this->pdo()->prepare(
            'UPDATE user_sessions SET selected_workspace_id = :workspace_id, '
            . 'selected_membership_id = :membership_id, tenant_context_selected_at = :selected_at, '
            . 'tenant_context_version = tenant_context_version + 1, updated_at = :updated_at '
            . "WHERE id = :session_id AND account_id = :account_id AND status = 'ACTIVE' "
            . 'AND tenant_context_version = :expected_version',
        );
        $statement->execute([
            ':workspace_id' => $workspace->workspaceInternalId,
            ':membership_id' => $workspace->membershipInternalId,
            ':selected_at' => self::format($selectedAt),
            ':updated_at' => self::format($selectedAt),
            ':session_id' => $account->sessionInternalId,
            ':account_id' => $account->accountInternalId,
            ':expected_version' => $expectedVersion->value,
        ]);

        return $statement->rowCount() === 1;
    }

    public function clear(
        AuthenticatedAccountContext $account,
        TenantContextVersion $expectedVersion,
        DateTimeImmutable $updatedAt,
    ): bool {
        $statement = $this->pdo()->prepare(
            'UPDATE user_sessions SET selected_workspace_id = NULL, selected_membership_id = NULL, '
            . 'tenant_context_selected_at = NULL, tenant_context_version = tenant_context_version + 1, '
            . "updated_at = :updated_at WHERE id = :session_id AND account_id = :account_id AND status = 'ACTIVE' "
            . 'AND tenant_context_version = :expected_version',
        );
        $statement->execute([
            ':updated_at' => self::format($updatedAt),
            ':session_id' => $account->sessionInternalId,
            ':account_id' => $account->accountInternalId,
            ':expected_version' => $expectedVersion->value,
        ]);

        return $statement->rowCount() === 1;
    }

    /** @param array<string, mixed> $row */
    private function option(array $row): WorkspaceContextOption
    {
        return new WorkspaceContextOption(
            self::integer($row, 'workspace_id'),
            WorkspaceId::fromBinary(self::string($row, 'public_id')),
            self::string($row, 'name'),
            WorkspaceStatus::from(self::string($row, 'workspace_status')),
            self::integer($row, 'workspace_version'),
            self::integer($row, 'membership_id'),
            UuidV7::fromBinary(self::string($row, 'membership_public_id')),
            MembershipStatus::from(self::string($row, 'membership_status')),
            self::integer($row, 'membership_version'),
            self::dateTime($row, 'workspace_updated_at'),
        );
    }

    private function pdo(): PDO
    {
        return $this->provider->connection();
    }

    private static function format(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }

    /** @param array<string, mixed> $row */
    private static function dateTime(array $row, string $key): DateTimeImmutable
    {
        $value = self::string($row, $key);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s.u', $value, new DateTimeZone('UTC'));
        if (!$parsed instanceof DateTimeImmutable) {
            throw new UnexpectedValueException('Tenant context persistence timestamp is invalid.');
        }

        return $parsed;
    }

    /** @param array<string, mixed> $row */
    private static function string(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value)) {
            throw new UnexpectedValueException('Tenant context persistence row is invalid.');
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    private static function nullableString(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;
        if ($value !== null && !is_string($value)) {
            throw new UnexpectedValueException('Tenant context persistence row is invalid.');
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    private static function integer(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value) || preg_match('/\A[0-9]+\z/', $value) !== 1) {
            throw new UnexpectedValueException('Tenant context persistence row is invalid.');
        }

        return (int)$value;
    }

    /** @param array<string, mixed> $row */
    private static function nullableInteger(array $row, string $key): ?int
    {
        if (($row[$key] ?? null) === null) {
            return null;
        }

        return self::integer($row, $key);
    }

    /** @return array<string, mixed>|null */
    private static function row(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }
        $row = [];
        foreach ($value as $key => $field) {
            if (!is_string($key)) {
                throw new UnexpectedValueException('Tenant context persistence row is invalid.');
            }
            $row[$key] = $field;
        }

        return $row;
    }
}
