<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

namespace Qmdb\Modules\SecurityPrivilegedAccess\Infrastructure\Persistence;

use PDO;
use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\SecurityPrivilegedAccess\Application\PrivilegedAccessHttpRepository;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessRequestId;
use Qmdb\Modules\SecurityPrivilegedAccess\Domain\PrivilegedAccessReviewId;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class MySqlPrivilegedAccessHttpRepository implements PrivilegedAccessHttpRepository
{
    public function __construct(private DatabaseConnectionProvider $provider)
    {
    }

    public function ownRequests(AuthenticatedAccountContext $actor, int $limit = 50): array
    {
        $limit = max(1, min(50, $limit));
        $statement = $this->provider->connection()->prepare(
            'SELECT request_record.public_id, request_record.access_type, request_record.scope_type, '
            . 'COALESCE(workspace.name, \'\') AS workspace_name, request_record.status, '
            . 'request_record.requested_duration_seconds, request_record.approved_duration_seconds, '
            . 'request_record.request_expires_at, review_record.status AS review_status '
            . 'FROM privileged_access_requests request_record LEFT JOIN workspaces workspace ON workspace.id = request_record.workspace_id '
            . 'LEFT JOIN privileged_access_reviews review_record ON review_record.request_id = request_record.id '
            . 'WHERE request_record.subject_account_id = :account_id ORDER BY request_record.created_at DESC, request_record.id DESC '
            . 'LIMIT ' . $limit,
        );
        $statement->execute([':account_id' => $actor->accountInternalId]);
        $items = [];
        while (is_array($raw = $statement->fetch(PDO::FETCH_ASSOC))) {
            $row = self::row($raw);
            $items[] = [
                'id' => UuidV7::fromBinary(self::string($row, 'public_id'))->toString(),
                'type' => self::string($row, 'access_type'),
                'scope' => self::string($row, 'scope_type'),
                'workspace_name' => self::string($row, 'workspace_name'),
                'status' => self::string($row, 'status'),
                'requested_duration' => self::integer($row, 'requested_duration_seconds'),
                'approved_duration' => self::nullableInteger($row, 'approved_duration_seconds'),
                'request_expires_at' => self::string($row, 'request_expires_at'),
                'review_status' => self::nullableString($row, 'review_status'),
            ];
        }

        return $items;
    }

    /**
     * @param array<mixed, mixed> $raw
     * @return array<string, mixed>
     */
    private static function row(array $raw): array
    {
        $row = [];
        foreach ($raw as $key => $value) {
            if (!is_string($key)) {
                throw new \UnexpectedValueException('Privileged-access HTTP projection is invalid.');
            }
            $row[$key] = $value;
        }

        return $row;
    }

    public function activeWorkspaceInternalId(string $workspacePublicId): ?int
    {
        try {
            $workspace = UuidV7::fromString($workspacePublicId);
        } catch (\InvalidArgumentException) {
            return null;
        }
        $statement = $this->provider->connection()->prepare(
            "SELECT id FROM workspaces WHERE public_id = :public_id AND status_code = 'ACTIVE' LIMIT 1",
        );
        $statement->bindValue(':public_id', $workspace->toBinary(), PDO::PARAM_LOB);
        $statement->execute();
        $value = $statement->fetchColumn();

        return is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : null);
    }

    public function reviewForRequest(PrivilegedAccessRequestId $requestId): ?PrivilegedAccessReviewId
    {
        $statement = $this->provider->connection()->prepare(
            'SELECT review_record.public_id FROM privileged_access_reviews review_record '
            . 'INNER JOIN privileged_access_requests request_record ON request_record.id = review_record.request_id '
            . 'WHERE request_record.public_id = :request_id '
            . 'AND review_record.status IN (\'PENDING\', \'OVERDUE\') LIMIT 1',
        );
        $statement->bindValue(':request_id', $requestId->toBinary(), PDO::PARAM_LOB);
        $statement->execute();
        $value = $statement->fetchColumn();

        return is_string($value) ? PrivilegedAccessReviewId::fromBinary($value) : null;
    }

    /** @param array<string, mixed> $row */
    private static function string(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value)) {
            throw new \UnexpectedValueException('Privileged-access HTTP projection is invalid.');
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    private static function nullableString(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;
        if ($value !== null && !is_string($value)) {
            throw new \UnexpectedValueException('Privileged-access HTTP projection is invalid.');
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
        if (!is_string($value) || !ctype_digit($value)) {
            throw new \UnexpectedValueException('Privileged-access HTTP projection is invalid.');
        }

        return (int) $value;
    }

    /** @param array<string, mixed> $row */
    private static function nullableInteger(array $row, string $key): ?int
    {
        return ($row[$key] ?? null) === null ? null : self::integer($row, $key);
    }
}
