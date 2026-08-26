<?php

declare(strict_types=1);

namespace Qmdb\Modules\Tenancy\Infrastructure\Persistence;

use DateTimeImmutable;
use PDO;
use Qmdb\Modules\Tenancy\Domain\Repository\WorkspaceRepository;
use Qmdb\Modules\Tenancy\Domain\Value\WorkspaceId;
use Qmdb\Modules\Tenancy\Domain\Workspace;
use Qmdb\Modules\Tenancy\Domain\WorkspaceStatus;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use UnexpectedValueException;

final readonly class MySqlWorkspaceRepository implements WorkspaceRepository
{
    public function __construct(private DatabaseConnectionProvider $provider)
    {
    }

    public function create(Workspace $workspace): int
    {
        $statement = $this->provider->connection()->prepare(
            'INSERT INTO workspaces '
            . '(public_id, workspace_code, name, status_code, version, created_at, updated_at) '
            . 'VALUES (:public_id, :workspace_code, :name, :status_code, :version, :created_at, :updated_at)',
        );
        $statement->bindValue(':public_id', $workspace->publicId->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':workspace_code', $workspace->code);
        $statement->bindValue(':name', $workspace->name);
        $statement->bindValue(':status_code', $workspace->status->value);
        $statement->bindValue(':version', $workspace->version, PDO::PARAM_INT);
        $statement->bindValue(':created_at', self::format($workspace->createdAt));
        $statement->bindValue(':updated_at', self::format($workspace->updatedAt));
        $statement->execute();

        return (int) $this->provider->connection()->lastInsertId();
    }

    public function byPublicId(WorkspaceId $workspaceId): ?Workspace
    {
        $statement = $this->provider->connection()->prepare(
            'SELECT id, public_id, workspace_code, name, status_code, version, created_at, updated_at '
            . 'FROM workspaces WHERE public_id = :public_id LIMIT 1',
        );
        $statement->bindValue(':public_id', $workspaceId->toBinary(), PDO::PARAM_LOB);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        return new Workspace(
            self::requiredInteger($row, 'id'),
            WorkspaceId::fromBinary(self::requiredString($row, 'public_id')),
            self::requiredString($row, 'workspace_code'),
            self::requiredString($row, 'name'),
            WorkspaceStatus::from(self::requiredString($row, 'status_code')),
            self::requiredInteger($row, 'version'),
            new DateTimeImmutable(self::requiredString($row, 'created_at')),
            new DateTimeImmutable(self::requiredString($row, 'updated_at')),
        );
    }

    public function changeStatus(
        WorkspaceId $workspaceId,
        WorkspaceStatus $status,
        int $expectedVersion,
        DateTimeImmutable $now,
    ): bool {
        $statement = $this->provider->connection()->prepare(
            'UPDATE workspaces SET status_code = :status_code, version = version + 1, updated_at = :updated_at '
            . 'WHERE public_id = :public_id AND version = :expected_version',
        );
        $statement->bindValue(':status_code', $status->value);
        $statement->bindValue(':updated_at', self::format($now));
        $statement->bindValue(':public_id', $workspaceId->toBinary(), PDO::PARAM_LOB);
        $statement->bindValue(':expected_version', $expectedVersion, PDO::PARAM_INT);
        $statement->execute();

        return $statement->rowCount() === 1;
    }

    private static function format(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }

    /** @param array<string, mixed> $row */
    private static function requiredString(array $row, string $column): string
    {
        $value = $row[$column] ?? null;
        if (!is_string($value)) {
            throw new UnexpectedValueException('Workspace persistence row has an invalid shape.');
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
            throw new UnexpectedValueException('Workspace persistence row has an invalid shape.');
        }

        return (int) $value;
    }
}
