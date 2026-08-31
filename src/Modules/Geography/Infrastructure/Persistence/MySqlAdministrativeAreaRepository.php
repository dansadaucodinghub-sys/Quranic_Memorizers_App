<?php

declare(strict_types=1);

namespace Qmdb\Modules\Geography\Infrastructure\Persistence;

use PDO;
use Qmdb\Modules\Geography\Domain\Repository\AdministrativeAreaRepository;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use UnexpectedValueException;

final readonly class MySqlAdministrativeAreaRepository implements AdministrativeAreaRepository
{
    public function __construct(private DatabaseConnectionProvider $provider)
    {
    }

    public function listActiveLevelOne(string $countryIsoAlpha2): array
    {
        $statement = $this->provider->connection()->prepare(self::select() . ' INNER JOIN geography_countries country ON country.id = area.country_id '
            . "WHERE country.iso_alpha2 = :country AND area.administrative_level = 1 AND area.status = 'ACTIVE' "
            . 'ORDER BY area.official_name ASC, area.id ASC LIMIT 37');
        $statement->bindValue(':country', $countryIsoAlpha2);
        $statement->execute();

        return self::areas($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findActiveLevelOneBySlug(string $countryIsoAlpha2, string $slug): ?array
    {
        $statement = $this->provider->connection()->prepare(self::select() . ' INNER JOIN geography_countries country ON country.id = area.country_id '
            . "WHERE country.iso_alpha2 = :country AND area.canonical_slug = :slug AND area.administrative_level = 1 "
            . "AND area.status = 'ACTIVE' LIMIT 1");
        $statement->bindValue(':country', $countryIsoAlpha2);
        $statement->bindValue(':slug', $slug);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : self::area(self::row($row));
    }

    public function findActiveByPublicId(string $publicId): ?array
    {
        $statement = $this->provider->connection()->prepare(self::select() . " WHERE area.public_id = UUID_TO_BIN(:public_id) AND area.status = 'ACTIVE' LIMIT 1");
        $statement->bindValue(':public_id', $publicId);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : self::area(self::row($row));
    }

    public function listActiveChildren(string $parentPublicId, int $limit = 60): array
    {
        $statement = $this->provider->connection()->prepare(self::select() . ' INNER JOIN geography_administrative_areas parent '
            . 'ON parent.id = area.parent_area_id AND parent.public_id = UUID_TO_BIN(:parent_public_id) '
            . "WHERE area.status = 'ACTIVE' ORDER BY area.official_name ASC, area.id ASC LIMIT :limit");
        $statement->bindValue(':parent_public_id', $parentPublicId);
        $statement->bindValue(':limit', max(1, min(60, $limit)), PDO::PARAM_INT);
        $statement->execute();

        return self::areas($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function searchActiveNigeria(string $query, int $limit = 30): array
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);
        $statement = $this->provider->connection()->prepare(
            'SELECT BIN_TO_UUID(area.public_id) AS public_id, area.canonical_code, area.canonical_slug, area.official_name, '
            . 'area.area_type, area.administrative_level, parent.canonical_slug AS parent_slug '
            . 'FROM geography_administrative_areas area INNER JOIN geography_countries country ON country.id = area.country_id '
            . 'LEFT JOIN geography_administrative_areas parent ON parent.id = area.parent_area_id '
            . "WHERE country.iso_alpha2 = 'NG' AND country.status = 'ACTIVE' AND area.status = 'ACTIVE' "
            . "AND area.search_name LIKE :pattern ESCAPE '\\\\' ORDER BY area.official_name ASC, area.id ASC LIMIT :limit",
        );
        $statement->bindValue(':pattern', '%' . strtolower($escaped) . '%');
        $statement->bindValue(':limit', max(1, min(30, $limit)), PDO::PARAM_INT);
        $statement->execute();
        $results = [];
        foreach (self::rows($statement->fetchAll(PDO::FETCH_ASSOC)) as $row) {
            $area = self::area($row);
            $parentSlug = $row['parent_slug'] ?? null;
            if ($parentSlug !== null && !is_string($parentSlug)) {
                throw new UnexpectedValueException('Administrative-area search parent is invalid.');
            }
            $results[] = [...$area, 'parent_slug' => $parentSlug];
        }

        return $results;
    }

    private static function select(): string
    {
        return 'SELECT BIN_TO_UUID(area.public_id) AS public_id, area.canonical_code, area.canonical_slug, '
            . 'area.official_name, area.area_type, area.administrative_level FROM geography_administrative_areas area';
    }

    /**
     * @param mixed $rows
     * @return list<array{public_id:string,canonical_code:string,canonical_slug:string,official_name:string,area_type:string,administrative_level:int}>
     */
    private static function areas(mixed $rows): array
    {
        $areas = [];
        foreach (self::rows($rows) as $row) {
            $areas[] = self::area($row);
        }

        return $areas;
    }

    /** @return list<array<string, mixed>> */
    private static function rows(mixed $rows): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new UnexpectedValueException('Administrative-area persistence rows have an invalid shape.');
        }

        $maps = [];
        foreach ($rows as $row) {
            if (!is_array($row) || array_is_list($row)) {
                throw new UnexpectedValueException('Administrative-area persistence row has an invalid shape.');
            }
            $map = [];
            foreach ($row as $key => $value) {
                if (!is_string($key)) {
                    throw new UnexpectedValueException('Administrative-area persistence row has an invalid key.');
                }
                $map[$key] = $value;
            }
            $maps[] = $map;
        }

        return $maps;
    }

    /** @return array<string, mixed> */
    private static function row(mixed $row): array
    {
        if (!is_array($row) || array_is_list($row)) {
            throw new UnexpectedValueException('Administrative-area persistence row has an invalid shape.');
        }

        $map = [];
        foreach ($row as $key => $value) {
            if (!is_string($key)) {
                throw new UnexpectedValueException('Administrative-area persistence row has an invalid key.');
            }
            $map[$key] = $value;
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $row
     * @return array{public_id:string,canonical_code:string,canonical_slug:string,official_name:string,area_type:string,administrative_level:int}
     */
    private static function area(array $row): array
    {
        foreach (['public_id', 'canonical_code', 'canonical_slug', 'official_name', 'area_type'] as $key) {
            if (!is_string($row[$key] ?? null)) {
                throw new UnexpectedValueException('Administrative-area persistence row has an invalid shape.');
            }
        }
        if (!is_numeric($row['administrative_level'] ?? null)) {
            throw new UnexpectedValueException('Administrative-area persistence row has an invalid level.');
        }

        return [
            'public_id' => $row['public_id'], 'canonical_code' => $row['canonical_code'],
            'canonical_slug' => $row['canonical_slug'], 'official_name' => $row['official_name'],
            'area_type' => $row['area_type'], 'administrative_level' => (int) $row['administrative_level'],
        ];
    }
}
