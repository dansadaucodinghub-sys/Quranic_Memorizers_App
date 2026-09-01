<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Infrastructure\Persistence;

use PDO;
use Qmdb\Modules\People\Application\PeopleProfilesVerificationProbe;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;

final readonly class MySqlPeopleProfilesVerificationProbe implements PeopleProfilesVerificationProbe
{
    public function __construct(private DatabaseConnectionProvider $database)
    {
    }

    public function report(): array
    {
        $connection = $this->database->connection();
        $tables = ['people_persons', 'people_person_names', 'people_account_links', 'people_person_geographies', 'people_role_profiles', 'people_memorizer_progress', 'people_guardianships'];
        $tableStatement = $connection->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name');
        if ($tableStatement === false) {
            throw new \RuntimeException('People-profile schema verification statement is unavailable.');
        }
        $invalid = 0;
        foreach ($tables as $table) {
            $tableStatement->execute(['table_name' => $table]);
            $invalid += (int) $tableStatement->fetchColumn() === 1 ? 0 : 1;
        }
        foreach (
            [
            "SELECT COUNT(*) FROM (SELECT account_id FROM people_account_links WHERE status = 'ACTIVE' AND link_type = 'SELF' GROUP BY account_id HAVING COUNT(*) > 1) invalid",
            "SELECT COUNT(*) FROM (SELECT person_id FROM people_account_links WHERE status = 'ACTIVE' AND link_type = 'SELF' GROUP BY person_id HAVING COUNT(*) > 1) invalid",
            "SELECT COUNT(*) FROM people_persons p WHERE p.status = 'ACTIVE' AND NOT EXISTS (SELECT 1 FROM people_person_names n WHERE n.person_id = p.id AND n.name_type = 'PRIMARY' AND n.status = 'ACTIVE')",
            "SELECT COUNT(*) FROM people_guardianships g WHERE g.status = 'ACTIVE' AND (g.guardian_person_id = g.dependent_person_id OR NOT EXISTS (SELECT 1 FROM people_role_profiles r WHERE r.person_id = g.guardian_person_id AND r.role_type = 'GUARDIAN' AND r.status = 'ACTIVE'))",
            "SELECT COUNT(*) FROM people_memorizer_progress mp INNER JOIN people_role_profiles r ON r.id = mp.role_profile_id WHERE r.person_id <> mp.person_id OR r.role_type <> 'MEMORIZER' OR r.status <> 'ACTIVE'",
            "SELECT COUNT(*) FROM people_person_geographies pg LEFT JOIN geography_administrative_areas l1 ON l1.id = pg.level_one_area_id LEFT JOIN geography_administrative_areas l2 ON l2.id = pg.level_two_area_id WHERE pg.status = 'ACTIVE' AND (l1.id IS NULL OR l1.country_id <> pg.country_id OR (pg.level_two_area_id IS NOT NULL AND (l2.id IS NULL OR l2.parent_area_id <> l1.id OR l2.country_id <> pg.country_id)))",
            ] as $query
        ) {
            $statement = $connection->query($query);
            if ($statement === false) {
                throw new \RuntimeException('People-profile verification query is unavailable.');
            }
            $invalid += (int) $statement->fetchColumn();
        }

        return [
            'person_count' => $this->count($connection, 'SELECT COUNT(*) FROM people_persons'),
            'self_linked_person_count' => $this->count($connection, "SELECT COUNT(*) FROM people_account_links WHERE status = 'ACTIVE' AND link_type = 'SELF'"),
            'dependent_person_count' => $this->count($connection, "SELECT COUNT(DISTINCT dependent_person_id) FROM people_guardianships WHERE status = 'ACTIVE'"),
            'active_role_profile_count' => $this->count($connection, "SELECT COUNT(*) FROM people_role_profiles WHERE status = 'ACTIVE'"),
            'active_guardianship_count' => $this->count($connection, "SELECT COUNT(*) FROM people_guardianships WHERE status = 'ACTIVE'"),
            'invalid_rows' => $invalid,
        ];
    }

    private function count(PDO $connection, string $query): int
    {
        $statement = $connection->query($query);
        if ($statement === false) {
            throw new \RuntimeException('People-profile count query is unavailable.');
        }

        return (int) $statement->fetchColumn();
    }
}
