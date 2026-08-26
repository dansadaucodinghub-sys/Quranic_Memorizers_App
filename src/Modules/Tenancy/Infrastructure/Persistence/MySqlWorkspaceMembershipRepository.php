<?php

declare(strict_types=1);

namespace Qmdb\Modules\Tenancy\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Qmdb\Modules\Tenancy\Application\TenantContext;
use Qmdb\Modules\Tenancy\Domain\MembershipStatus;
use Qmdb\Modules\Tenancy\Domain\Repository\WorkspaceMembershipRepository;
use Qmdb\Modules\Tenancy\Domain\WorkspaceMembership;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;
use UnexpectedValueException;

final readonly class MySqlWorkspaceMembershipRepository implements WorkspaceMembershipRepository
{
    public function __construct(private DatabaseConnectionProvider $provider)
    {
    }

    public function create(TenantContext $context, WorkspaceMembership $membership): int
    {
        if ($context->workspaceInternalId() !== $membership->workspaceInternalId) {
            throw new \DomainException('Tenant membership scope does not match trusted context.');
        }
        $statement = $this->provider->connection()->prepare(
            'INSERT INTO workspace_memberships '
            . '(public_id, workspace_id, user_account_id, status_code, version, created_at, updated_at) '
            . 'VALUES (:public_id, :workspace_id, :user_account_id, :status_code, :version, :created_at, :updated_at)',
        );
        $statement->bindValue(':public_id', $membership->publicId->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':workspace_id', $context->workspaceInternalId(), PDO::PARAM_INT);
        $statement->bindValue(':user_account_id', $membership->accountInternalId, PDO::PARAM_INT);
        $statement->bindValue(':status_code', $membership->status->value);
        $statement->bindValue(':version', $membership->version, PDO::PARAM_INT);
        $statement->bindValue(':created_at', self::format($membership->createdAt));
        $statement->bindValue(':updated_at', self::format($membership->updatedAt));
        $statement->execute();

        return (int) $this->provider->connection()->lastInsertId();
    }

    public function forAccount(TenantContext $context, int $accountInternalId, int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));
        $statement = $this->provider->connection()->prepare(
            'SELECT id, public_id, workspace_id, user_account_id, status_code, version, created_at, updated_at '
            . 'FROM workspace_memberships WHERE workspace_id = :workspace_id '
            . 'AND user_account_id = :user_account_id ORDER BY id LIMIT :row_limit',
        );
        $statement->bindValue(':workspace_id', $context->workspaceInternalId(), PDO::PARAM_INT);
        $statement->bindValue(':user_account_id', $accountInternalId, PDO::PARAM_INT);
        $statement->bindValue(':row_limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        $memberships = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                throw new UnexpectedValueException('Membership persistence row is invalid.');
            }
            $memberships[] = new WorkspaceMembership(
                self::requiredInteger($row, 'id'),
                UuidV7::fromBinary(self::requiredString($row, 'public_id')),
                self::requiredInteger($row, 'workspace_id'),
                self::requiredInteger($row, 'user_account_id'),
                MembershipStatus::from(self::requiredString($row, 'status_code')),
                self::requiredInteger($row, 'version'),
                new DateTimeImmutable(self::requiredString($row, 'created_at')),
                new DateTimeImmutable(self::requiredString($row, 'updated_at')),
            );
        }

        return $memberships;
    }

    private static function format(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }

    /** @param array<string, mixed> $row */
    private static function requiredString(array $row, string $column): string
    {
        $value = $row[$column] ?? null;
        if (!is_string($value)) {
            throw new UnexpectedValueException('Membership persistence row has an invalid shape.');
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    private static function requiredInteger(array $row, string $column): int
    {
        $value = $row[$column] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value) || preg_match('/\A[0-9]+\z/', $value) !== 1) {
            throw new UnexpectedValueException('Membership persistence row has an invalid shape.');
        }

        return (int) $value;
    }
}
