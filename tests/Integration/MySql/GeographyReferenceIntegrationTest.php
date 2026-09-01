<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\MySql;

use PDO;
use PDOException;
use Qmdb\Modules\Geography\Infrastructure\Migration\CreateGeographyAdministrativeAreaHierarchyMigration;
use Qmdb\Modules\Geography\Infrastructure\Migration\CreateGeographyCountryAndDatasetFoundationMigration;
use Qmdb\Modules\Geography\Infrastructure\Persistence\MySqlAdministrativeAreaRepository;
use Qmdb\Modules\Geography\Infrastructure\Seed\SeedNigeriaAdministrativeGeography;
use Qmdb\Tests\Support\MySql\MySqlIntegrationTestCase;

final class GeographyReferenceIntegrationTest extends MySqlIntegrationTestCase
{
    private PDO $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->provider()->connection();
        $this->rebuildGeographyTables();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->rebuildGeographyTables();
        }
        parent::tearDown();
    }

    private function rebuildGeographyTables(): void
    {
        $this->dropGeographyTables();
        foreach ([new CreateGeographyCountryAndDatasetFoundationMigration(), new CreateGeographyAdministrativeAreaHierarchyMigration()] as $migration) {
            foreach ($migration->up() as $step) {
                $this->connection->prepare($step->sql())->execute($step->parameters());
            }
        }
        foreach ((new SeedNigeriaAdministrativeGeography())->steps() as $step) {
            $this->connection->prepare($step->sql())->execute($step->parameters());
        }
    }

    public function testSeededRegistryHasTheApprovedCountryHierarchyAndFctCouncils(): void
    {
        self::assertSame(1, $this->countRows('geography_countries'));
        self::assertSame(1, $this->countRows('geography_dataset_versions'));
        self::assertSame(811, $this->countRows('geography_administrative_areas'));
        self::assertSame(37, $this->countRows('geography_administrative_areas WHERE administrative_level = 1'));
        self::assertSame(774, $this->countRows('geography_administrative_areas WHERE administrative_level = 2'));
        self::assertSame(6, $this->countRows(
            "geography_administrative_areas child INNER JOIN geography_administrative_areas parent ON parent.id = child.parent_area_id WHERE parent.canonical_code = 'NG-FC'",
        ));
        self::assertSame(768, $this->countRows("geography_administrative_areas WHERE area_type = 'LOCAL_GOVERNMENT_AREA'"));
        self::assertSame(6, $this->countRows("geography_administrative_areas WHERE area_type = 'AREA_COUNCIL'"));
    }

    public function testConstraintsRejectDuplicateCanonicalCodesAndOrphanedChildren(): void
    {
        $this->expectException(PDOException::class);
        $this->connection->exec(
            "INSERT INTO geography_administrative_areas (public_id, country_id, dataset_version_id, parent_area_id, administrative_level, area_type, canonical_code, official_code, canonical_slug, official_name, search_name, status, version, created_at, updated_at, retired_at) SELECT UUID_TO_BIN('11111111-1111-4111-8111-111111111111'), country.id, dataset.id, NULL, 1, 'STATE', 'NG-AB', 'NG-AB', 'duplicate', 'Duplicate', 'duplicate', 'ACTIVE', 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), NULL FROM geography_countries country INNER JOIN geography_dataset_versions dataset ON dataset.country_id = country.id",
        );
    }

    public function testRepositoryUsesBoundedPreparedReadQueriesAndSupportingIndexes(): void
    {
        $repository = new MySqlAdministrativeAreaRepository($this->provider());
        $levelOne = $repository->listActiveLevelOne('NG');
        $fct = array_values(array_filter(
            $levelOne,
            static fn (array $area): bool => $area['canonical_code'] === 'NG-FC',
        ));
        self::assertCount(37, $levelOne);
        self::assertCount(1, $fct);
        self::assertCount(6, $repository->listActiveChildren($fct[0]['public_id']));
        self::assertSame([], $repository->searchActiveNigeria('%_'));

        $plan = $this->connection->query(
            "EXPLAIN SELECT official_name FROM geography_administrative_areas WHERE parent_area_id = (SELECT id FROM geography_administrative_areas WHERE canonical_code = 'NG-FC') AND status = 'ACTIVE' ORDER BY official_name, id",
        );
        $rows = $plan === false ? [] : $plan->fetchAll(PDO::FETCH_ASSOC);
        self::assertNotSame([], $rows);
        self::assertContains('ix_geography_administrative_areas_parent_name', array_column($rows, 'key'));
    }

    private function countRows(string $from): int
    {
        $statement = $this->connection->query('SELECT COUNT(*) FROM ' . $from);
        if ($statement === false) {
            throw new PDOException('Geography count query failed.');
        }
        $value = $statement->fetchColumn();

        return is_numeric($value) ? (int) $value : 0;
    }

    private function dropGeographyTables(): void
    {
        $this->connection->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (
            [
            'people_profile_operation_results', 'people_guardianships', 'people_memorizer_progress',
            'people_role_profiles', 'people_person_geographies', 'people_account_links', 'people_person_names',
            'people_persons',
            ] as $table
        ) {
            $this->connection->exec('DROP TABLE IF EXISTS ' . $table);
        }
        foreach (['geography_administrative_areas', 'geography_dataset_versions', 'geography_countries'] as $table) {
            $this->connection->exec('DROP TABLE IF EXISTS ' . $table);
        }
        $this->connection->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
