<?php

declare(strict_types=1);

namespace Qmdb\Modules\Organizations\Infrastructure\Persistence;

use DateTimeImmutable;
use PDO;
use PDOStatement;
use Qmdb\Modules\Organizations\Application\OrganizationRegistryRepository;
use Qmdb\Modules\Organizations\Configuration\OrganizationsRegistryConfiguration;
use Qmdb\Modules\Tenancy\Application\TenantContext;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

/**
 * @phpstan-import-type InputData from OrganizationRegistryRepository
 * @phpstan-import-type Record from OrganizationRegistryRepository
 */
final readonly class MySqlOrganizationRegistryRepository implements OrganizationRegistryRepository
{
    public function __construct(private DatabaseConnectionProvider $provider, private OrganizationsRegistryConfiguration $configuration)
    {
    }

    /** @return list<Record> */
    public function list(TenantContext $tenant, string $search, int $limit): array
    {
        $statement = $this->db()->prepare("SELECT BIN_TO_UUID(o.public_id) public_id,o.registry_code,o.status,o.version,n.display_name,(SELECT c.code FROM organization_classification_assignments a INNER JOIN organization_classifications c ON c.id=a.classification_id WHERE a.workspace_id=o.workspace_id AND a.organization_id=o.id AND a.status='ACTIVE' AND a.is_primary=1 LIMIT 1) primary_classification FROM organizations o INNER JOIN organization_names n ON n.workspace_id=o.workspace_id AND n.organization_id=o.id AND n.name_type='PRIMARY' AND n.status='ACTIVE' WHERE o.workspace_id=:workspace_id AND (:search_empty='' OR n.search_name LIKE CONCAT(:search_prefix,'%')) ORDER BY n.search_name,o.id LIMIT :limit");
        $normalized = $this->search($search);
        $statement->bindValue(':workspace_id', $tenant->workspaceInternalId(), PDO::PARAM_INT);
        $statement->bindValue(':search_empty', $normalized);
        $statement->bindValue(':search_prefix', $normalized);
        $statement->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $statement->execute();
        return $this->rows($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return Record|null */
    public function organization(TenantContext $tenant, string $publicId, bool $forUpdate = false): ?array
    {
        $sql = "SELECT o.id,o.public_id,o.workspace_id,o.registry_code,o.status,o.version,n.display_name,n.script_code,n.version name_version,j.jurisdiction_level,BIN_TO_UUID(c.public_id) jurisdiction_country_id,BIN_TO_UUID(l1.public_id) jurisdiction_level_one_id,BIN_TO_UUID(l2.public_id) jurisdiction_level_two_id,u.id primary_unit_id,BIN_TO_UUID(u.public_id) primary_unit_public_id,u.version primary_unit_version,un.display_name unit_name,un.script_code unit_script,u.unit_type FROM organizations o INNER JOIN organization_names n ON n.workspace_id=o.workspace_id AND n.organization_id=o.id AND n.name_type='PRIMARY' AND n.status='ACTIVE' INNER JOIN organization_jurisdictions j ON j.workspace_id=o.workspace_id AND j.organization_id=o.id AND j.status='ACTIVE' LEFT JOIN geography_countries c ON c.id=j.country_id LEFT JOIN geography_administrative_areas l1 ON l1.id=j.level_one_area_id LEFT JOIN geography_administrative_areas l2 ON l2.id=j.level_two_area_id LEFT JOIN organization_units u ON u.workspace_id=o.workspace_id AND u.organization_id=o.id AND u.is_primary=1 LEFT JOIN organization_unit_names un ON un.workspace_id=o.workspace_id AND un.organization_id=o.id AND un.unit_id=u.id AND un.name_type='PRIMARY' AND un.status='ACTIVE' WHERE o.workspace_id=:workspace_id AND o.public_id=UUID_TO_BIN(:public_id) LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
        $statement = $this->db()->prepare($sql);
        $statement->execute(['workspace_id' => $tenant->workspaceInternalId(), 'public_id' => $publicId]);
        $record = $this->row($statement->fetch(PDO::FETCH_ASSOC));
        if ($record === null) {
            return null;
        }
        $record['classification_codes'] = $this->classificationCodes($tenant, $this->integer($record, 'id'));
        return $record;
    }

    /** @return list<Record> */
    public function classifications(): array
    {
        $statement = $this->db()->query("SELECT BIN_TO_UUID(public_id) public_id,code,sort_order FROM organization_classifications WHERE status='ACTIVE' ORDER BY sort_order,code");
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Organization classifications query cannot be prepared.');
        }

        return $this->rows($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @param InputData $input
     * @return Record
     */
    public function create(TenantContext $tenant, int $accountId, string $publicId, string $code, array $input, DateTimeImmutable $now): array
    {
        unset($now);
        $db = $this->db();
        $statement = $db->prepare("INSERT INTO organizations(public_id,workspace_id,registry_code,status,created_by_account_id,version,created_at,updated_at,retired_at) VALUES(UUID_TO_BIN(:public_id),:workspace_id,:registry_code,'ACTIVE',:account_id,1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL)");
        $statement->execute(['public_id' => $publicId, 'workspace_id' => $tenant->workspaceInternalId(), 'registry_code' => $code, 'account_id' => $accountId]);
        $organizationId = (int) $db->lastInsertId();
        $this->insertName('organization_names', $tenant->workspaceInternalId(), $organizationId, null, $this->inputText($input, 'primary_name'), $this->inputText($input, 'primary_script'));
        $this->insertClassifications($tenant, $organizationId, $input);
        $this->insertJurisdiction($tenant, $organizationId, $input);
        $unitId = $this->insertUnit($tenant, $organizationId, null, $accountId, $this->inputText($input, 'unit_name'), $this->inputText($input, 'unit_script'), $this->inputText($input, 'unit_type'), true, 0);
        $this->insertLocation($tenant, $organizationId, $unitId, $input);
        return $this->required($this->organization($tenant, $publicId, true));
    }

    /**
     * @param Record $organization
     * @param InputData $input
     * @return Record
     */
    public function update(TenantContext $tenant, array $organization, array $input, int $expectedVersion, DateTimeImmutable $now): array
    {
        unset($now);
        $this->assertVersion($organization, $expectedVersion);
        $statement = $this->db()->prepare("UPDATE organizations SET version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=:organization_id AND workspace_id=:workspace_id AND status='ACTIVE' AND version=:version");
        $statement->execute(['organization_id' => $this->integer($organization, 'id'), 'workspace_id' => $tenant->workspaceInternalId(), 'version' => $expectedVersion]);
        if ($statement->rowCount() !== 1) {
            throw new \DomainException('Organization is stale or unavailable.');
        }
        $organizationId = $this->integer($organization, 'id');
        $this->supersedeAndInsertName('organization_names', $tenant->workspaceInternalId(), $organizationId, null, $this->inputText($input, 'primary_name'), $this->inputText($input, 'primary_script'));
        $this->replaceClassifications($tenant, $organizationId, $input);
        $this->replaceJurisdiction($tenant, $organizationId, $input);
        return $this->required($this->organization($tenant, UuidV7::fromBinary($this->text($organization, 'public_id'))->toString(), true));
    }

    /** @param Record $organization */
    public function retire(TenantContext $tenant, array $organization, int $expectedVersion, DateTimeImmutable $now): int
    {
        unset($now);
        $this->assertVersion($organization, $expectedVersion);
        $db = $this->db();
        $open = $db->prepare("SELECT 1 FROM organization_affiliations WHERE workspace_id=:workspace_id AND organization_id=:organization_id AND status IN ('PENDING_ACCEPTANCE','ACTIVE','SUSPENDED') LIMIT 1 FOR UPDATE");
        $organizationId = $this->integer($organization, 'id');
        $open->execute(['workspace_id' => $tenant->workspaceInternalId(), 'organization_id' => $organizationId]);
        if ($open->fetchColumn() !== false) {
            throw new \DomainException('Organization has open affiliations.');
        }
        $statement = $db->prepare("UPDATE organizations SET status='RETIRED',retired_at=UTC_TIMESTAMP(6),version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=:organization_id AND workspace_id=:workspace_id AND status='ACTIVE' AND version=:version");
        $statement->execute(['organization_id' => $organizationId, 'workspace_id' => $tenant->workspaceInternalId(), 'version' => $expectedVersion]);
        if ($statement->rowCount() !== 1) {
            throw new \DomainException('Organization is stale or unavailable.');
        }
        $units = $db->prepare("UPDATE organization_units SET status='RETIRED',retired_at=UTC_TIMESTAMP(6),version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE workspace_id=:workspace_id AND organization_id=:organization_id AND status='ACTIVE'");
        $units->execute(['workspace_id' => $tenant->workspaceInternalId(), 'organization_id' => $organizationId]);
        return $units->rowCount();
    }

    /** @param Record $organization @return list<Record> */
    public function units(TenantContext $tenant, array $organization): array
    {
        $statement = $this->db()->prepare("SELECT BIN_TO_UUID(u.public_id) public_id,u.registry_code,u.unit_type,u.status,u.depth,u.version,BIN_TO_UUID(p.public_id) parent_public_id,n.display_name FROM organization_units u INNER JOIN organization_unit_names n ON n.workspace_id=u.workspace_id AND n.organization_id=u.organization_id AND n.unit_id=u.id AND n.name_type='PRIMARY' AND n.status='ACTIVE' LEFT JOIN organization_units p ON p.id=u.parent_unit_id AND p.workspace_id=u.workspace_id AND p.organization_id=u.organization_id WHERE u.workspace_id=:workspace_id AND u.organization_id=:organization_id ORDER BY u.depth,n.search_name,u.id");
        $statement->execute(['workspace_id' => $tenant->workspaceInternalId(), 'organization_id' => $this->integer($organization, 'id')]);
        return $this->rows($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @param Record $organization @return Record|null */
    public function unit(TenantContext $tenant, array $organization, string $publicId, bool $forUpdate = false): ?array
    {
        $sql = "SELECT u.id,u.public_id,u.parent_unit_id,u.registry_code,u.unit_type,u.status,u.depth,u.version,n.display_name,n.script_code,n.version name_version,BIN_TO_UUID(c.public_id) location_country_id,BIN_TO_UUID(l1.public_id) location_level_one_id,BIN_TO_UUID(l2.public_id) location_level_two_id FROM organization_units u INNER JOIN organization_unit_names n ON n.workspace_id=u.workspace_id AND n.organization_id=u.organization_id AND n.unit_id=u.id AND n.name_type='PRIMARY' AND n.status='ACTIVE' LEFT JOIN organization_unit_locations loc ON loc.workspace_id=u.workspace_id AND loc.organization_id=u.organization_id AND loc.unit_id=u.id AND loc.status='ACTIVE' LEFT JOIN geography_countries c ON c.id=loc.country_id LEFT JOIN geography_administrative_areas l1 ON l1.id=loc.level_one_area_id LEFT JOIN geography_administrative_areas l2 ON l2.id=loc.level_two_area_id WHERE u.workspace_id=:workspace_id AND u.organization_id=:organization_id AND u.public_id=UUID_TO_BIN(:public_id) LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
        $statement = $this->db()->prepare($sql);
        $statement->execute(['workspace_id' => $tenant->workspaceInternalId(), 'organization_id' => $this->integer($organization, 'id'), 'public_id' => $publicId]);
        return $this->row($statement->fetch(PDO::FETCH_ASSOC));
    }

    /**
     * @param Record $organization
     * @param InputData $input
     * @param Record|null $parent
     * @return Record
     */
    public function createChildUnit(TenantContext $tenant, array $organization, array $input, ?array $parent, int $accountId, string $publicId, string $code, DateTimeImmutable $now): array
    {
        unset($now);
        if ($parent === null) {
            throw new \InvalidArgumentException('Organization Unit parent is required.');
        }
        if ($this->text($parent, 'status') !== 'ACTIVE') {
            throw new \DomainException('Organization Unit parent is unavailable.');
        }
        $depth = $this->integer($parent, 'depth') + 1;
        if ($depth > $this->configuration->unitMaximumDepth) {
            throw new \DomainException('Organization Unit maximum depth has been reached.');
        }
        $count = $this->db()->prepare('SELECT COUNT(*) FROM organization_units WHERE workspace_id=:workspace_id AND organization_id=:organization_id');
        $organizationId = $this->integer($organization, 'id');
        $count->execute(['workspace_id' => $tenant->workspaceInternalId(), 'organization_id' => $organizationId]);
        if ((int) $count->fetchColumn() >= $this->configuration->maximumUnits) {
            throw new \DomainException('Organization Unit capacity has been reached.');
        }
        $unitId = $this->insertUnit($tenant, $organizationId, $this->integer($parent, 'id'), $accountId, $this->inputText($input, 'unit_name'), $this->inputText($input, 'unit_script'), $this->inputText($input, 'unit_type'), false, $depth, $publicId, $code);
        $this->insertLocation($tenant, $organizationId, $unitId, $input);
        return $this->required($this->unit($tenant, $organization, $publicId, true));
    }

    /**
     * @param Record $organization
     * @param Record $unit
     * @param InputData $input
     * @return Record
     */
    public function updateUnit(TenantContext $tenant, array $organization, array $unit, array $input, int $expectedVersion, DateTimeImmutable $now): array
    {
        unset($now);
        $this->assertVersion($unit, $expectedVersion);
        $statement = $this->db()->prepare("UPDATE organization_units SET unit_type=:unit_type,version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=:unit_id AND workspace_id=:workspace_id AND organization_id=:organization_id AND status='ACTIVE' AND version=:version");
        $organizationId = $this->integer($organization, 'id');
        $unitId = $this->integer($unit, 'id');
        $statement->execute(['unit_type' => $this->inputText($input, 'unit_type'), 'unit_id' => $unitId, 'workspace_id' => $tenant->workspaceInternalId(), 'organization_id' => $organizationId, 'version' => $expectedVersion]);
        if ($statement->rowCount() !== 1) {
            throw new \DomainException('Organization Unit is stale or unavailable.');
        }
        $this->supersedeAndInsertName('organization_unit_names', $tenant->workspaceInternalId(), $organizationId, $unitId, $this->inputText($input, 'unit_name'), $this->inputText($input, 'unit_script'));
        $this->replaceLocation($tenant, $organizationId, $unitId, $input);
        return $this->required($this->unit($tenant, $organization, UuidV7::fromBinary($this->text($unit, 'public_id'))->toString(), true));
    }

    /** @param Record $organization @param Record $unit */
    public function retireUnit(TenantContext $tenant, array $organization, array $unit, int $expectedVersion, DateTimeImmutable $now): void
    {
        unset($now);
        $this->assertVersion($unit, $expectedVersion);
        if ($this->integer($unit, 'depth') === 0) {
            throw new \DomainException('The primary Unit cannot be retired independently.');
        }
        $assignments = $this->db()->prepare("SELECT 1 FROM organization_affiliation_unit_assignments WHERE workspace_id=:workspace_id AND organization_id=:organization_id AND unit_id=:unit_id AND status IN ('PROPOSED','ACTIVE') LIMIT 1 FOR UPDATE");
        $organizationId = $this->integer($organization, 'id');
        $unitId = $this->integer($unit, 'id');
        $assignments->execute(['workspace_id' => $tenant->workspaceInternalId(), 'organization_id' => $organizationId, 'unit_id' => $unitId]);
        if ($assignments->fetchColumn() !== false) {
            throw new \DomainException('Organization Unit has open affiliation assignments.');
        }
        $children = $this->db()->prepare("SELECT COUNT(*) FROM organization_units WHERE workspace_id=:workspace_id AND organization_id=:organization_id AND parent_unit_id=:unit_id AND status='ACTIVE'");
        $children->execute(['workspace_id' => $tenant->workspaceInternalId(), 'organization_id' => $organizationId, 'unit_id' => $unitId]);
        if ((int) $children->fetchColumn() > 0) {
            throw new \DomainException('Organization Unit has active children.');
        }
        $statement = $this->db()->prepare("UPDATE organization_units SET status='RETIRED',retired_at=UTC_TIMESTAMP(6),version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=:unit_id AND workspace_id=:workspace_id AND organization_id=:organization_id AND status='ACTIVE' AND version=:version");
        $statement->execute(['unit_id' => $unitId, 'workspace_id' => $tenant->workspaceInternalId(), 'organization_id' => $organizationId, 'version' => $expectedVersion]);
        if ($statement->rowCount() !== 1) {
            throw new \DomainException('Organization Unit is stale or unavailable.');
        }
    }

    public function report(): array
    {
        $db = $this->db();
        unset($db);
        $organizations = $this->count('SELECT COUNT(*) FROM organizations');
        $units = $this->count('SELECT COUNT(*) FROM organization_units');
        $invalid = $this->count("SELECT (SELECT COUNT(*) FROM organizations o WHERE NOT EXISTS(SELECT 1 FROM organization_names n WHERE n.workspace_id=o.workspace_id AND n.organization_id=o.id AND n.name_type='PRIMARY' AND n.status='ACTIVE'))+(SELECT COUNT(*) FROM organizations o WHERE NOT EXISTS(SELECT 1 FROM organization_classification_assignments a WHERE a.workspace_id=o.workspace_id AND a.organization_id=o.id AND a.status='ACTIVE' AND a.is_primary=1))");
        return ['organizations' => $organizations, 'units' => $units, 'invalid_rows' => $invalid];
    }

    private function insertUnit(TenantContext $tenant, int $organizationId, ?int $parentId, int $accountId, string $name, string $script, string $type, bool $primary, int $depth, ?string $publicId = null, ?string $code = null): int
    {
        $publicId ??= UuidV7::generate()->toString();
        $code ??= 'QMU-' . bin2hex(random_bytes(10));
        $statement = $this->db()->prepare("INSERT INTO organization_units(public_id,workspace_id,organization_id,parent_unit_id,registry_code,unit_type,is_primary,depth,status,created_by_account_id,version,created_at,updated_at,retired_at) VALUES(UUID_TO_BIN(:public_id),:workspace_id,:organization_id,:parent_id,:registry_code,:unit_type,:is_primary,:depth,'ACTIVE',:account_id,1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL)");
        $statement->execute(['public_id' => $publicId, 'workspace_id' => $tenant->workspaceInternalId(), 'organization_id' => $organizationId, 'parent_id' => $parentId, 'registry_code' => $code, 'unit_type' => $type, 'is_primary' => $primary ? 1 : 0, 'depth' => $depth, 'account_id' => $accountId]);
        $unitId = (int) $this->db()->lastInsertId();
        $this->insertName('organization_unit_names', $tenant->workspaceInternalId(), $organizationId, $unitId, $name, $script);
        return $unitId;
    }

    private function insertName(string $table, int $workspaceId, int $organizationId, ?int $unitId, string $name, string $script): void
    {
        $columns = $unitId === null ? 'workspace_id,organization_id' : 'workspace_id,organization_id,unit_id';
        $values = $unitId === null ? ':workspace_id,:organization_id' : ':workspace_id,:organization_id,:unit_id';
        $statement = $this->db()->prepare("INSERT INTO {$table}(public_id,{$columns},name_type,script_code,display_name,search_name,status,version,effective_at,superseded_at,created_at,updated_at) VALUES(UUID_TO_BIN(:public_id),{$values},'PRIMARY',:script_code,:display_name,:search_name,'ACTIVE',1,UTC_TIMESTAMP(6),NULL,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");
        $parameters = ['public_id' => UuidV7::generate()->toString(), 'workspace_id' => $workspaceId, 'organization_id' => $organizationId, 'script_code' => $script, 'display_name' => $name, 'search_name' => $this->search($name)];
        if ($unitId !== null) {
            $parameters['unit_id'] = $unitId;
        }
        $statement->execute($parameters);
    }

    private function supersedeAndInsertName(string $table, int $workspaceId, int $organizationId, ?int $unitId, string $name, string $script): void
    {
        $where = $unitId === null ? 'workspace_id=:workspace_id AND organization_id=:organization_id' : 'workspace_id=:workspace_id AND organization_id=:organization_id AND unit_id=:unit_id';
        $statement = $this->db()->prepare("UPDATE {$table} SET status='SUPERSEDED',superseded_at=UTC_TIMESTAMP(6),version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE {$where} AND name_type='PRIMARY' AND status='ACTIVE'");
        $parameters = ['workspace_id' => $workspaceId, 'organization_id' => $organizationId];
        if ($unitId !== null) {
            $parameters['unit_id'] = $unitId;
        }
        $statement->execute($parameters);
        if ($statement->rowCount() !== 1) {
            throw new \DomainException('Current Organization name is unavailable.');
        }
        $this->insertName($table, $workspaceId, $organizationId, $unitId, $name, $script);
    }

    /** @param InputData $input */
    private function insertClassifications(TenantContext $tenant, int $organizationId, array $input): void
    {
        foreach ($this->inputList($input, 'classification_codes') as $code) {
            $statement = $this->db()->prepare("INSERT INTO organization_classification_assignments(public_id,workspace_id,organization_id,classification_id,is_primary,status,version,effective_at,removed_at,created_at,updated_at) SELECT UUID_TO_BIN(:public_id),:workspace_id,:organization_id,c.id,:is_primary,'ACTIVE',1,UTC_TIMESTAMP(6),NULL,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6) FROM organization_classifications c WHERE c.code=:code AND c.status='ACTIVE'");
            $statement->execute(['public_id' => UuidV7::generate()->toString(), 'workspace_id' => $tenant->workspaceInternalId(), 'organization_id' => $organizationId, 'is_primary' => $code === $this->inputText($input, 'primary_classification') ? 1 : 0, 'code' => $code]);
            if ($statement->rowCount() !== 1) {
                throw new \InvalidArgumentException('Organization classification is unavailable.');
            }
        }
    }

    /** @param InputData $input */
    private function replaceClassifications(TenantContext $tenant, int $organizationId, array $input): void
    {
        $statement = $this->db()->prepare("UPDATE organization_classification_assignments SET status='REMOVED',removed_at=UTC_TIMESTAMP(6),version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE workspace_id=:workspace_id AND organization_id=:organization_id AND status='ACTIVE'");
        $statement->execute(['workspace_id' => $tenant->workspaceInternalId(), 'organization_id' => $organizationId]);
        $this->insertClassifications($tenant, $organizationId, $input);
    }

    /** @param InputData $input */
    private function insertJurisdiction(TenantContext $tenant, int $organizationId, array $input): void
    {
        $statement = $this->db()->prepare("INSERT INTO organization_jurisdictions(public_id,workspace_id,organization_id,jurisdiction_level,country_id,level_one_area_id,level_two_area_id,source_type,status,version,effective_at,superseded_at,created_at,updated_at) VALUES(UUID_TO_BIN(:public_id),:workspace_id,:organization_id,:jurisdiction_level,(SELECT id FROM geography_countries WHERE public_id=UUID_TO_BIN(:country_public_id)),(SELECT id FROM geography_administrative_areas WHERE public_id=UUID_TO_BIN(:level_one_public_id)),(SELECT id FROM geography_administrative_areas WHERE public_id=UUID_TO_BIN(:level_two_public_id)),'SELF_DECLARED','ACTIVE',1,UTC_TIMESTAMP(6),NULL,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");
        $statement->execute(['public_id' => UuidV7::generate()->toString(), 'workspace_id' => $tenant->workspaceInternalId(), 'organization_id' => $organizationId, 'jurisdiction_level' => $this->inputText($input, 'jurisdiction_level'), 'country_public_id' => $this->inputNullableText($input, 'country_public_id'), 'level_one_public_id' => $this->inputNullableText($input, 'level_one_public_id'), 'level_two_public_id' => $this->inputNullableText($input, 'level_two_public_id')]);
    }

    /** @param InputData $input */
    private function replaceJurisdiction(TenantContext $tenant, int $organizationId, array $input): void
    {
        $statement = $this->db()->prepare("UPDATE organization_jurisdictions SET status='SUPERSEDED',superseded_at=UTC_TIMESTAMP(6),version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE workspace_id=:workspace_id AND organization_id=:organization_id AND status='ACTIVE'");
        $statement->execute(['workspace_id' => $tenant->workspaceInternalId(), 'organization_id' => $organizationId]);
        $this->insertJurisdiction($tenant, $organizationId, $input);
    }

    /** @param InputData $input */
    private function insertLocation(TenantContext $tenant, int $organizationId, int $unitId, array $input): void
    {
        if ($this->inputNullableText($input, 'unit_country_public_id') === null) {
            return;
        }
        $statement = $this->db()->prepare("INSERT INTO organization_unit_locations(public_id,workspace_id,organization_id,unit_id,country_id,level_one_area_id,level_two_area_id,source_type,status,version,effective_at,superseded_at,created_at,updated_at) VALUES(UUID_TO_BIN(:public_id),:workspace_id,:organization_id,:unit_id,(SELECT id FROM geography_countries WHERE public_id=UUID_TO_BIN(:country_public_id)),(SELECT id FROM geography_administrative_areas WHERE public_id=UUID_TO_BIN(:level_one_public_id)),(SELECT id FROM geography_administrative_areas WHERE public_id=UUID_TO_BIN(:level_two_public_id)),'SELF_DECLARED','ACTIVE',1,UTC_TIMESTAMP(6),NULL,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");
        $statement->execute(['public_id' => UuidV7::generate()->toString(), 'workspace_id' => $tenant->workspaceInternalId(), 'organization_id' => $organizationId, 'unit_id' => $unitId, 'country_public_id' => $this->inputNullableText($input, 'unit_country_public_id'), 'level_one_public_id' => $this->inputNullableText($input, 'unit_level_one_public_id'), 'level_two_public_id' => $this->inputNullableText($input, 'unit_level_two_public_id')]);
    }

    /** @param InputData $input */
    private function replaceLocation(TenantContext $tenant, int $organizationId, int $unitId, array $input): void
    {
        $statement = $this->db()->prepare("UPDATE organization_unit_locations SET status='SUPERSEDED',superseded_at=UTC_TIMESTAMP(6),version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE workspace_id=:workspace_id AND organization_id=:organization_id AND unit_id=:unit_id AND status='ACTIVE'");
        $statement->execute(['workspace_id' => $tenant->workspaceInternalId(), 'organization_id' => $organizationId, 'unit_id' => $unitId]);
        $this->insertLocation($tenant, $organizationId, $unitId, $input);
    }

    /** @return list<string> */
    private function classificationCodes(TenantContext $tenant, int $organizationId): array
    {
        $statement = $this->db()->prepare("SELECT c.code FROM organization_classification_assignments a INNER JOIN organization_classifications c ON c.id=a.classification_id WHERE a.workspace_id=:workspace_id AND a.organization_id=:organization_id AND a.status='ACTIVE' ORDER BY a.is_primary DESC,c.sort_order,c.code");
        $statement->execute(['workspace_id' => $tenant->workspaceInternalId(), 'organization_id' => $organizationId]);
        $codes = [];
        foreach ($this->rows($statement->fetchAll(PDO::FETCH_ASSOC)) as $row) {
            $codes[] = $this->text($row, 'code');
        }

        return $codes;
    }

    private function db(): PDO
    {
        return $this->provider->connection();
    }

    /** @return Record|null */
    private function row(mixed $row): ?array
    {
        if (!is_array($row)) {
            return null;
        }

        $normalized = [];
        foreach ($row as $key => $value) {
            if (!is_string($key) || (!is_int($value) && !is_string($value) && $value !== null)) {
                throw new \UnexpectedValueException('Organization query row is invalid.');
            }
            $normalized[$key] = $value;
        }

        return $normalized;
    }

    /** @return list<Record> */
    private function rows(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        $normalized = [];
        foreach ($rows as $row) {
            $value = $this->row($row);
            if ($value === null) {
                throw new \UnexpectedValueException('Organization query result is invalid.');
            }
            $normalized[] = $value;
        }

        return $normalized;
    }

    /** @param Record $row */
    private function text(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value)) {
            throw new \UnexpectedValueException('Organization query text value is invalid.');
        }

        return $value;
    }

    /** @param Record $row */
    private function integer(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value) || preg_match('/\A-?[0-9]+\z/', $value) !== 1) {
            throw new \UnexpectedValueException('Organization query integer value is invalid.');
        }

        return (int) $value;
    }

    /** @param InputData $input */
    private function inputText(array $input, string $key): string
    {
        $value = $input[$key] ?? null;
        if (!is_string($value)) {
            throw new \UnexpectedValueException('Organization persistence input text is invalid.');
        }

        return $value;
    }

    /** @param InputData $input */
    private function inputNullableText(array $input, string $key): ?string
    {
        $value = $input[$key] ?? null;
        if ($value === null) {
            return null;
        }

        return $this->inputText($input, $key);
    }

    /**
     * @param InputData $input
     * @return list<string>
     */
    private function inputList(array $input, string $key): array
    {
        $value = $input[$key] ?? null;
        if (!is_array($value)) {
            throw new \UnexpectedValueException('Organization persistence input list is invalid.');
        }

        return $value;
    }

    private function count(string $sql): int
    {
        $statement = $this->db()->query($sql);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Organization count query cannot be prepared.');
        }

        $value = $statement->fetchColumn();
        if (!is_int($value) && !is_string($value)) {
            throw new \UnexpectedValueException('Organization count is invalid.');
        }

        return (int) $value;
    }

    private function search(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($name)) ?? '', 'UTF-8');
    }
    /**
     * @param Record|null $record
     * @return Record
     */
    private function required(?array $record): array
    {
        if ($record === null) {
            throw new \UnexpectedValueException('Organization persistence result is unavailable.');
        }

        return $record;
    }

    /** @param Record $record */
    private function assertVersion(array $record, int $expectedVersion): void
    {
        if ($this->integer($record, 'version') !== $expectedVersion) {
            throw new \DomainException('Organization record is stale.');
        }
    }
}
