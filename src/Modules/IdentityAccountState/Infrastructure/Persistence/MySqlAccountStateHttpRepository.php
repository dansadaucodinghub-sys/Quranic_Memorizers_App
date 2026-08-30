<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccountState\Infrastructure\Persistence;

use PDO;
use Qmdb\Modules\IdentityAccountState\Application\AccountStateHttpRepository;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Database\Result\DatabaseResult;
use Qmdb\Shared\Identifier\UuidV7;

final readonly class MySqlAccountStateHttpRepository implements AccountStateHttpRepository
{
    public function __construct(private DatabaseConnectionProvider $provider)
    {
    }

    public function findSafeAccount(string $accountPublicId): ?array
    {
        $statement = $this->provider->connection()->prepare(
            'SELECT account.public_id, account.account_status, account.version, account.created_at, account.updated_at, '
            . "MAX(CASE WHEN status_event.event_type = 'ACTIVATED' THEN status_event.occurred_at END) AS activated_at, "
            . "MAX(CASE WHEN status_event.event_type = 'SUSPENDED' THEN status_event.occurred_at END) AS suspended_at "
            . 'FROM user_accounts account LEFT JOIN account_status_events status_event ON status_event.user_account_id = account.id '
            . 'WHERE account.public_id = UUID_TO_BIN(:public_id) GROUP BY account.id, account.public_id, account.account_status, '
            . 'account.version, account.created_at, account.updated_at',
        );
        $statement->execute([':public_id' => UuidV7::fromString($accountPublicId)->toString()]);
        $row = DatabaseResult::nullableRow($statement->fetch(PDO::FETCH_ASSOC), 'Account-state HTTP');
        if ($row === null) {
            return null;
        }

        return [
            'public_id' => UuidV7::fromBinary(DatabaseResult::string($row['public_id'] ?? null, 'Account public ID'))->toString(),
            'status' => DatabaseResult::string($row['account_status'] ?? null, 'Account status'),
            'version' => DatabaseResult::integer($row['version'] ?? null, 'Account version'),
            'created_at' => DatabaseResult::string($row['created_at'] ?? null, 'Account creation time'),
            'updated_at' => DatabaseResult::string($row['updated_at'] ?? null, 'Account update time'),
            'activated_at' => DatabaseResult::nullableString($row['activated_at'] ?? null, 'Account activation time'),
            'suspended_at' => DatabaseResult::nullableString($row['suspended_at'] ?? null, 'Account suspension time'),
        ];
    }
}
