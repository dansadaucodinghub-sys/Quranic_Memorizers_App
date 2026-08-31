<?php

declare(strict_types=1);

namespace Qmdb\Modules\Geography\Infrastructure\Persistence;

use PDO;
use Qmdb\Modules\Geography\Application\GeographyReferenceReadinessProbe;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Throwable;

final readonly class MySqlGeographyReferenceReadinessProbe implements GeographyReferenceReadinessProbe
{
    public function __construct(private DatabaseConnectionProvider $provider)
    {
    }

    public function isActiveProjectionValid(): bool
    {
        try {
            $countStatement = $this->provider->connection()->query(
                "SELECT COUNT(*) AS areas, SUM(administrative_level = 1) AS level_one, SUM(administrative_level = 2) AS level_two, "
                . "SUM(area_type = 'STATE') AS states, SUM(area_type = 'FEDERAL_CAPITAL_TERRITORY') AS fct, "
                . "SUM(area_type = 'LOCAL_GOVERNMENT_AREA') AS lgas, SUM(area_type = 'AREA_COUNCIL') AS councils, "
                . 'SUM(administrative_level = 2 AND parent_area_id IS NULL) AS orphans FROM geography_administrative_areas '
                . "WHERE status = 'ACTIVE'",
            );
            if ($countStatement === false) {
                return false;
            }
            $counts = $countStatement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($counts) || $this->countValues($counts) !== [811, 37, 774, 36, 1, 768, 6, 0]) {
                return false;
            }
            $activeStatement = $this->provider->connection()->query(
                "SELECT COUNT(*) FROM geography_dataset_versions dataset INNER JOIN geography_countries country ON country.id = dataset.country_id "
                . "WHERE country.iso_alpha2 = 'NG' AND country.status = 'ACTIVE' AND dataset.status = 'ACTIVE' "
                . 'AND OCTET_LENGTH(dataset.content_sha256) = 32 AND dataset.record_count = 811',
            );
            if ($activeStatement === false) {
                return false;
            }
            $active = $activeStatement->fetchColumn();

            return (int) $active === 1;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param array<mixed, mixed> $counts
     * @return list<int>
     */
    private function countValues(array $counts): array
    {
        $values = [];
        foreach (['areas', 'level_one', 'level_two', 'states', 'fct', 'lgas', 'councils', 'orphans'] as $key) {
            $value = $counts[$key] ?? null;
            if (!is_numeric($value)) {
                return [];
            }
            $values[] = (int) $value;
        }

        return $values;
    }
}
