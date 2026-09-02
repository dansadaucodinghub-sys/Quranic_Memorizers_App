<?php

declare(strict_types=1);

namespace Qmdb\Modules\OrganizationAffiliations\Infrastructure\Persistence;

use PDO;
use Qmdb\Modules\OrganizationAffiliations\Application\OrganizationAffiliationPersonCanonicalizationParticipant;
use Qmdb\Modules\People\Application\PersonCanonicalizationPreflight;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;

/**
 * Deliberately narrow: it can only inspect/reassign existing affiliation
 * Person references; it cannot create, end, or otherwise administer them.
 */
final readonly class MySqlOrganizationAffiliationCanonicalizationParticipant implements OrganizationAffiliationPersonCanonicalizationParticipant
{
    public function __construct(private DatabaseConnectionProvider $provider)
    {
    }

    public function preflight(int $sourcePersonId, int $canonicalPersonId, int $maximumAffectedRecords): PersonCanonicalizationPreflight
    {
        $connection = $this->provider->connection();
        $count = $connection->prepare('SELECT COUNT(*) FROM organization_affiliations WHERE person_id = :source_person_id');
        $count->execute(['source_person_id' => $sourcePersonId]);
        $affected = (int) $count->fetchColumn();
        $conflicts = [];
        if ($affected > $maximumAffectedRecords) {
            $conflicts[] = 'AFFECTED_RECORD_LIMIT_EXCEEDED';
        }
        $sameOrganization = $connection->prepare("SELECT 1 FROM organization_affiliations source INNER JOIN organization_affiliations target ON target.workspace_id = source.workspace_id AND target.organization_id = source.organization_id AND target.person_id = :canonical_person_id AND target.status IN ('PENDING_ACCEPTANCE','ACTIVE','SUSPENDED') WHERE source.person_id = :source_person_id AND source.status IN ('PENDING_ACCEPTANCE','ACTIVE','SUSPENDED') LIMIT 1");
        $sameOrganization->execute(['source_person_id' => $sourcePersonId, 'canonical_person_id' => $canonicalPersonId]);
        if ($sameOrganization->fetchColumn() !== false) {
            $conflicts[] = 'ORGANIZATION_AFFILIATION_CONFLICT';
        }

        return new PersonCanonicalizationPreflight($conflicts, $affected);
    }

    public function apply(int $sourcePersonId, int $canonicalPersonId): int
    {
        $statement = $this->provider->connection()->prepare('UPDATE organization_affiliations SET person_id = :canonical_person_id, version = version + 1, updated_at = UTC_TIMESTAMP(6) WHERE person_id = :source_person_id');
        $statement->bindValue(':source_person_id', $sourcePersonId, PDO::PARAM_INT);
        $statement->bindValue(':canonical_person_id', $canonicalPersonId, PDO::PARAM_INT);
        $statement->execute();

        return $statement->rowCount();
    }
}
