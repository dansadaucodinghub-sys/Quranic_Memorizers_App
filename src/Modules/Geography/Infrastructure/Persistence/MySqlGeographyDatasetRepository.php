<?php

declare(strict_types=1);

namespace Qmdb\Modules\Geography\Infrastructure\Persistence;

use PDO;
use Qmdb\Modules\Geography\Domain\Repository\GeographyDatasetRepository;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use UnexpectedValueException;

final readonly class MySqlGeographyDatasetRepository implements GeographyDatasetRepository
{
    public function __construct(private DatabaseConnectionProvider $provider)
    {
    }

    public function findActiveForCountry(string $isoAlpha2): ?array
    {
        $statement = $this->provider->connection()->prepare(
            'SELECT BIN_TO_UUID(dataset.public_id) AS public_id, dataset.dataset_code, dataset.dataset_version, '
            . 'HEX(dataset.content_sha256) AS content_sha256, dataset.record_count '
            . 'FROM geography_dataset_versions dataset INNER JOIN geography_countries country ON country.id = dataset.country_id '
            . "WHERE country.iso_alpha2 = :code AND country.status = 'ACTIVE' AND dataset.status = 'ACTIVE' LIMIT 1",
        );
        $statement->bindValue(':code', $isoAlpha2);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (
            !is_array($row) || !is_string($row['public_id'] ?? null) || !is_string($row['dataset_code'] ?? null)
            || !is_string($row['dataset_version'] ?? null) || !is_string($row['content_sha256'] ?? null)
            || !is_numeric($row['record_count'] ?? null)
        ) {
            return null;
        }

        return [
            'public_id' => $row['public_id'], 'dataset_code' => $row['dataset_code'],
            'dataset_version' => $row['dataset_version'], 'content_sha256' => strtolower($row['content_sha256']),
            'record_count' => (int) $row['record_count'],
        ];
    }
}
