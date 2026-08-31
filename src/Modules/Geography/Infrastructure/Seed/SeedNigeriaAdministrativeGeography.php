<?php

declare(strict_types=1);

namespace Qmdb\Modules\Geography\Infrastructure\Seed;

use DateTimeImmutable;
use DateTimeZone;
use Qmdb\Modules\Geography\Infrastructure\Dataset\NigeriaAdministrativeGeographyDataset;
use Qmdb\Modules\Geography\Infrastructure\Dataset\NigeriaAdministrativeGeographyDatasetLoader;
use Qmdb\Modules\Geography\Infrastructure\Dataset\NigeriaAdministrativeGeographyDatasetValidator;
use Qmdb\Shared\Schema\Seed\Seed;
use Qmdb\Shared\Schema\Seed\SeedId;
use Qmdb\Shared\Schema\Seed\SeedStepId;
use Qmdb\Shared\Schema\Seed\SqlSeedStep;
use RuntimeException;

final readonly class SeedNigeriaAdministrativeGeography implements Seed
{
    private NigeriaAdministrativeGeographyDataset $dataset;

    /** @var list<SqlSeedStep> */
    private array $steps;

    public function __construct(?string $projectRoot = null)
    {
        $root = $projectRoot ?? dirname(__DIR__, 5);
        $this->dataset = (new NigeriaAdministrativeGeographyDatasetLoader($root))->load();
        $report = (new NigeriaAdministrativeGeographyDatasetValidator())->validate($this->dataset);
        if (!$report->isValid()) {
            throw new RuntimeException('Nigeria geography dataset validation failed: ' . $report->failureSummary());
        }
        $this->steps = $this->buildSteps($this->dataset);
    }

    public function id(): SeedId
    {
        return new SeedId('20260831020100_seed_nigeria_administrative_geography');
    }

    public function description(): string
    {
        return 'Seed Nigeria country, provenance, states, FCT, LGAs, and Area Councils.';
    }

    public function dependencies(): array
    {
        return [];
    }

    public function steps(): array
    {
        return $this->steps;
    }

    public function dataset(): NigeriaAdministrativeGeographyDataset
    {
        return $this->dataset;
    }

    /** @return list<SqlSeedStep> */
    private function buildSteps(NigeriaAdministrativeGeographyDataset $dataset): array
    {
        $country = $dataset->country();
        $metadata = $dataset->metadata();
        $now = self::timestamp();
        $steps = [new SqlSeedStep(
            new SeedStepId('001_insert_nigeria_country'),
            'Insert the immutable Nigeria country reference record.',
            <<<'SQL'
INSERT INTO geography_countries
    (public_id, iso_alpha2, iso_alpha3, iso_numeric, common_name, official_name, canonical_slug,
        status, version, created_at, updated_at, retired_at)
VALUES
    (UUID_TO_BIN(:public_id), :iso_alpha2, :iso_alpha3, :iso_numeric, :common_name, :official_name,
        :canonical_slug, :status, :version, :created_at, :updated_at, NULL)
SQL,
            [
                ':public_id' => self::string($country, 'public_id'), ':iso_alpha2' => self::string($country, 'iso_alpha2'),
                ':iso_alpha3' => self::string($country, 'iso_alpha3'), ':iso_numeric' => self::string($country, 'iso_numeric'),
                ':common_name' => self::string($country, 'common_name'), ':official_name' => self::string($country, 'official_name'),
                ':canonical_slug' => self::string($country, 'canonical_slug'), ':status' => self::string($country, 'status'),
                ':version' => self::integer($country, 'version'), ':created_at' => $now, ':updated_at' => $now,
            ],
        ), new SqlSeedStep(
            new SeedStepId('002_insert_nigeria_dataset_version'),
            'Insert the active checksum-governed Nigeria geography dataset version.',
            <<<'SQL'
INSERT INTO geography_dataset_versions
    (public_id, country_id, dataset_code, dataset_version, source_authority, source_title, source_reference,
        source_published_on, source_retrieved_at, content_sha256, record_count, status, version, imported_at,
        created_at, updated_at, superseded_at)
SELECT UUID_TO_BIN(:public_id), country.id, :dataset_code, :dataset_version, :source_authority, :source_title,
    :source_reference, :source_published_on, :source_retrieved_at, UNHEX(:content_sha256), :record_count,
    'ACTIVE', 1, :imported_at, :created_at, :updated_at, NULL
FROM geography_countries country
WHERE country.iso_alpha2 = 'NG' AND country.status = 'ACTIVE'
SQL,
            [
                ':public_id' => self::datasetPublicId(), ':dataset_code' => self::string($metadata, 'dataset_code'),
                ':dataset_version' => self::string($metadata, 'dataset_version'),
                ':source_authority' => self::string($metadata, 'source_authority'), ':source_title' => self::string($metadata, 'source_title'),
                ':source_reference' => self::string($metadata, 'source_reference'), ':source_published_on' => self::string($metadata, 'source_published_on'),
                ':source_retrieved_at' => self::dateTime(self::string($metadata, 'source_retrieved_at')),
                ':content_sha256' => self::string($metadata, 'content_sha256'), ':record_count' => 811,
                ':imported_at' => $now, ':created_at' => $now, ':updated_at' => $now,
            ],
        )];
        $position = 3;
        foreach ([1, 2] as $level) {
            foreach ($dataset->areas() as $area) {
                if (self::integer($area, 'administrative_level') !== $level) {
                    continue;
                }
                $steps[] = new SqlSeedStep(
                    new SeedStepId(sprintf('%03d_insert_area_%s', $position++, strtolower(str_replace('-', '_', self::string($area, 'canonical_code'))))),
                    'Insert immutable Nigeria administrative reference area ' . self::string($area, 'canonical_code') . '.',
                    $level === 1 ? self::levelOneSql() : self::levelTwoSql(),
                    self::areaParameters($area, $now, $level === 2),
                );
            }
        }

        return $steps;
    }

    private static function levelOneSql(): string
    {
        return <<<'SQL'
INSERT INTO geography_administrative_areas
    (public_id, country_id, dataset_version_id, parent_area_id, administrative_level, area_type, canonical_code,
        official_code, canonical_slug, official_name, search_name, status, version, created_at, updated_at, retired_at)
SELECT UUID_TO_BIN(:public_id), country.id, dataset.id, NULL, :administrative_level, :area_type,
    :canonical_code, :official_code, :canonical_slug, :official_name, :search_name, 'ACTIVE', 1,
    :created_at, :updated_at, NULL
FROM geography_countries country
INNER JOIN geography_dataset_versions dataset ON dataset.country_id = country.id AND dataset.status = 'ACTIVE'
WHERE country.iso_alpha2 = 'NG' AND country.status = 'ACTIVE'
SQL;
    }

    private static function levelTwoSql(): string
    {
        return <<<'SQL'
INSERT INTO geography_administrative_areas
    (public_id, country_id, dataset_version_id, parent_area_id, administrative_level, area_type, canonical_code,
        official_code, canonical_slug, official_name, search_name, status, version, created_at, updated_at, retired_at)
SELECT UUID_TO_BIN(:public_id), country.id, dataset.id, parent.id, :administrative_level, :area_type,
    :canonical_code, :official_code, :canonical_slug, :official_name, :search_name, 'ACTIVE', 1,
    :created_at, :updated_at, NULL
FROM geography_countries country
INNER JOIN geography_dataset_versions dataset ON dataset.country_id = country.id AND dataset.status = 'ACTIVE'
INNER JOIN geography_administrative_areas parent
    ON parent.country_id = country.id AND parent.canonical_code = :parent_canonical_code AND parent.status = 'ACTIVE'
WHERE country.iso_alpha2 = 'NG' AND country.status = 'ACTIVE'
SQL;
    }

    /** @param array<string, mixed> $area
     * @return array<string, bool|float|int|string|null>
     */
    private static function areaParameters(array $area, string $now, bool $hasParent): array
    {
        $parameters = [
            ':public_id' => self::string($area, 'public_id'), ':administrative_level' => self::integer($area, 'administrative_level'),
            ':area_type' => self::string($area, 'area_type'), ':canonical_code' => self::string($area, 'canonical_code'),
            ':official_code' => isset($area['official_code']) && is_string($area['official_code']) ? $area['official_code'] : null,
            ':canonical_slug' => self::string($area, 'canonical_slug'), ':official_name' => self::string($area, 'official_name'),
            ':search_name' => self::string($area, 'search_name'), ':created_at' => $now, ':updated_at' => $now,
        ];
        if ($hasParent) {
            $parameters[':parent_canonical_code'] = self::string($area, 'parent_canonical_code');
        }

        return $parameters;
    }

    /** @param array<string, mixed> $values */
    private static function string(array $values, string $key): string
    {
        $value = $values[$key] ?? null;
        if (!is_string($value)) {
            throw new RuntimeException('Validated geography dataset field is missing: ' . $key);
        }

        return $value;
    }

    /** @param array<string, mixed> $values */
    private static function integer(array $values, string $key): int
    {
        $value = $values[$key] ?? null;
        if (!is_int($value)) {
            throw new RuntimeException('Validated geography dataset integer field is missing: ' . $key);
        }

        return $value;
    }

    private static function timestamp(): string
    {
        return (new DateTimeImmutable('2026-08-31T20:00:00.000000Z'))->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s.u');
    }

    private static function dateTime(string $value): string
    {
        return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }

    private static function datasetPublicId(): string
    {
        return '6ddbe463-2e48-4f1f-bf4d-0702fb73e15a';
    }
}
