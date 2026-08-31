<?php

declare(strict_types=1);

namespace Qmdb\Modules\Geography\Infrastructure\Persistence;

use PDO;
use Qmdb\Modules\Geography\Domain\Repository\CountryRepository;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use UnexpectedValueException;

final readonly class MySqlCountryRepository implements CountryRepository
{
    public function __construct(private DatabaseConnectionProvider $provider)
    {
    }

    public function findActiveByIsoAlpha2(string $code): ?array
    {
        $statement = $this->provider->connection()->prepare(
            'SELECT BIN_TO_UUID(public_id) AS public_id, iso_alpha2, iso_alpha3, iso_numeric, common_name, '
            . 'official_name, canonical_slug FROM geography_countries '
            . "WHERE iso_alpha2 = :code AND status = 'ACTIVE' LIMIT 1",
        );
        $statement->bindValue(':code', $code);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : self::country(self::row($row));
    }

    /** @return array<string, mixed> */
    private static function row(mixed $row): array
    {
        if (!is_array($row) || array_is_list($row)) {
            throw new UnexpectedValueException('Country persistence row has an invalid shape.');
        }

        $map = [];
        foreach ($row as $key => $value) {
            if (!is_string($key)) {
                throw new UnexpectedValueException('Country persistence row has an invalid key.');
            }
            $map[$key] = $value;
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $row
     * @return array{public_id:string,iso_alpha2:string,iso_alpha3:string,iso_numeric:string,common_name:string,official_name:string,canonical_slug:string}
     */
    private static function country(array $row): array
    {
        $required = ['public_id', 'iso_alpha2', 'iso_alpha3', 'iso_numeric', 'common_name', 'official_name', 'canonical_slug'];
        foreach ($required as $key) {
            if (!is_string($row[$key] ?? null)) {
                throw new UnexpectedValueException('Country persistence row has an invalid shape.');
            }
        }

        return [
            'public_id' => $row['public_id'],
            'iso_alpha2' => $row['iso_alpha2'],
            'iso_alpha3' => $row['iso_alpha3'],
            'iso_numeric' => $row['iso_numeric'],
            'common_name' => $row['common_name'],
            'official_name' => $row['official_name'],
            'canonical_slug' => $row['canonical_slug'],
        ];
    }
}
