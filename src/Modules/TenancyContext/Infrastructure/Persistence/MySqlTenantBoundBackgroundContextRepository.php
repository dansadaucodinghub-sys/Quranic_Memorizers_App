<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Infrastructure\Persistence;

use PDO;
use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\Tenancy\Domain\Value\WorkspaceId;
use Qmdb\Modules\TenancyContext\Application\Background\AccountTenantBoundBackgroundJob;
use Qmdb\Modules\TenancyContext\Application\Background\TenantBoundBackgroundContextRepository;
use Qmdb\Modules\TenancyContext\Application\Background\TenantBoundBackgroundJobExecutionContext;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class MySqlTenantBoundBackgroundContextRepository implements TenantBoundBackgroundContextRepository
{
    public function __construct(private DatabaseConnectionProvider $provider)
    {
    }

    public function resolve(AccountTenantBoundBackgroundJob $job): ?TenantBoundBackgroundJobExecutionContext
    {
        $statement = $this->provider->connection()->prepare(
            'SELECT a.id AS account_id, a.public_id AS account_public_id, w.id AS workspace_id, '
            . 'w.public_id AS workspace_public_id, m.id AS membership_id, m.public_id AS membership_public_id '
            . 'FROM workspace_memberships m '
            . 'INNER JOIN user_accounts a ON a.id = m.user_account_id '
            . 'INNER JOIN workspaces w ON w.id = m.workspace_id '
            . "WHERE a.public_id = :account_public_id AND a.account_status = 'ACTIVE' "
            . "AND w.public_id = :workspace_public_id AND w.status_code = 'ACTIVE' "
            . "AND m.public_id = :membership_public_id AND m.status_code = 'ACTIVE' LIMIT 1",
        );
        $statement->bindValue(':account_public_id', $job->accountId()->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':workspace_public_id', $job->workspaceId()->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':membership_public_id', $job->membershipId()->toBinary(), PDO::PARAM_LOB);
        $statement->execute();
        $row = self::row($statement->fetch(PDO::FETCH_ASSOC));
        if (
            $row === null || !is_string($row['account_public_id'] ?? null)
            || !is_string($row['workspace_public_id'] ?? null)
            || !is_string($row['membership_public_id'] ?? null)
        ) {
            return null;
        }

        return new TenantBoundBackgroundJobExecutionContext(
            self::integer($row, 'account_id'),
            AccountId::fromBinary($row['account_public_id']),
            self::integer($row, 'workspace_id'),
            WorkspaceId::fromBinary($row['workspace_public_id']),
            self::integer($row, 'membership_id'),
            UuidV7::fromBinary($row['membership_public_id']),
        );
    }

    /** @param array<string, mixed> $row */
    private static function integer(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value) || preg_match('/\A[1-9][0-9]*\z/', $value) !== 1) {
            throw new \UnexpectedValueException('Tenant-bound background context row is invalid.');
        }

        return (int)$value;
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
                throw new \UnexpectedValueException('Tenant-bound background context row is invalid.');
            }
            $row[$key] = $field;
        }

        return $row;
    }
}
