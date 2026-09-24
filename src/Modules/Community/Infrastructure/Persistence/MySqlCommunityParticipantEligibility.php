<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Infrastructure\Persistence;

use PDO;
use PDOStatement;
use Qmdb\Modules\Community\Application\CommunityParticipantEligibility;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;

final readonly class MySqlCommunityParticipantEligibility implements CommunityParticipantEligibility
{
    public function __construct(private DatabaseConnectionProvider $connections)
    {
    }

    public function requireAdultSelfLinkedAccount(int $accountId): void
    {
        if (!$this->connections->connection()->inTransaction()) {
            throw new \LogicException('Community participation checks require a transaction.');
        }
        $statement = $this->connections->connection()->prepare(<<<'SQL'
SELECT p.id FROM user_accounts a
JOIN people_account_links l ON l.account_id=a.id AND l.link_type='SELF' AND l.status='ACTIVE'
JOIN people_persons p ON p.id=l.person_id AND p.status='ACTIVE'
WHERE a.id=:account AND a.account_status='ACTIVE' AND p.birth_date IS NOT NULL
  AND p.birth_date<=DATE_SUB(UTC_DATE(), INTERVAL 18 YEAR)
LIMIT 1
FOR SHARE
SQL);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Could not prepare community eligibility check.');
        }
        $statement->execute(['account' => $accountId]);
        if ($statement->fetch(PDO::FETCH_ASSOC) === false) {
            throw new \DomainException('Community participation is unavailable.');
        }
    }
}
