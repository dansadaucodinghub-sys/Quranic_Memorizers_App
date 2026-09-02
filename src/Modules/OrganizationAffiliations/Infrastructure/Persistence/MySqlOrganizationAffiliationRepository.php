<?php

declare(strict_types=1);

namespace Qmdb\Modules\OrganizationAffiliations\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOStatement;
use Qmdb\Modules\OrganizationAffiliations\Application\OrganizationAffiliationRepository;
use Qmdb\Modules\Tenancy\Application\TenantContext;
use Qmdb\Shared\Database\Connection\DatabaseConnectionProvider;
use Qmdb\Shared\Identifier\UuidV7;

/** @phpstan-import-type Row from OrganizationAffiliationRepository */
final readonly class MySqlOrganizationAffiliationRepository implements OrganizationAffiliationRepository
{
    public function __construct(private DatabaseConnectionProvider $provider)
    {
    }

    public function organization(TenantContext $tenant, string $organizationPublicId, bool $forUpdate = false): ?array
    {
        $statement = $this->db()->prepare('SELECT id,BIN_TO_UUID(public_id) AS public_id,status,version FROM organizations WHERE workspace_id=:workspace_id AND public_id=UUID_TO_BIN(:public_id) LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''));
        $statement->execute(['workspace_id' => $tenant->workspaceInternalId(),'public_id' => $organizationPublicId]);
        return $this->row($statement->fetch(PDO::FETCH_ASSOC));
    }

    public function candidateByRegistryCode(string $registryCode): ?array
    {
        $statement = $this->db()->prepare("SELECT p.id,BIN_TO_UUID(p.public_id) AS public_id,p.status FROM people_persons p WHERE p.registry_code=:registry_code AND p.status='ACTIVE' LIMIT 1");
        $statement->execute(['registry_code' => $registryCode]);
        return $this->row($statement->fetch(PDO::FETCH_ASSOC));
    }

    public function activeRoleDefinitions(array $codes): array
    {
        if ($codes === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($codes), '?'));
        $statement = $this->db()->prepare("SELECT id,BIN_TO_UUID(public_id) AS public_id,code,category,sensitivity_level,required_person_role_type,status,sort_order FROM organization_affiliation_role_definitions WHERE status='ACTIVE' AND code IN ($placeholders) ORDER BY sort_order,id");
        $statement->execute($codes);
        return $this->rows($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function activeUnits(TenantContext $tenant, array $organization, array $publicIds): array
    {
        if ($publicIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($publicIds), '?'));
        $statement = $this->db()->prepare("SELECT id,BIN_TO_UUID(public_id) AS public_id,status FROM organization_units WHERE workspace_id=? AND organization_id=? AND status='ACTIVE' AND public_id IN (" . implode(',', array_fill(0, count($publicIds), 'UUID_TO_BIN(?)')) . ')');
        $statement->execute(array_merge([$tenant->workspaceInternalId(), (int) $organization['id']], $publicIds));
        return $this->rows($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function openAffiliation(TenantContext $tenant, array $organization, int $personInternalId, bool $forUpdate = false): ?array
    {
        $statement = $this->db()->prepare("SELECT id,BIN_TO_UUID(public_id) AS public_id,affiliation_code,workspace_id,organization_id,person_id,status,version,request_expires_at,requested_by_account_id FROM organization_affiliations WHERE workspace_id=:workspace_id AND organization_id=:organization_id AND person_id=:person_id AND status IN ('PENDING_ACCEPTANCE','ACTIVE','SUSPENDED') LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : ''));
        $statement->execute(['workspace_id' => $tenant->workspaceInternalId(),'organization_id' => (int)$organization['id'],'person_id' => $personInternalId]);
        return $this->row($statement->fetch(PDO::FETCH_ASSOC));
    }

    public function affiliation(TenantContext $tenant, array $organization, string $affiliationPublicId, bool $forUpdate = false): ?array
    {
        $statement = $this->db()->prepare('SELECT id,BIN_TO_UUID(public_id) AS public_id,affiliation_code,workspace_id,organization_id,person_id,status,version,request_expires_at,requested_by_account_id FROM organization_affiliations WHERE workspace_id=:workspace_id AND organization_id=:organization_id AND public_id=UUID_TO_BIN(:public_id) LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''));
        $statement->execute(['workspace_id' => $tenant->workspaceInternalId(),'organization_id' => (int)$organization['id'],'public_id' => $affiliationPublicId]);
        return $this->row($statement->fetch(PDO::FETCH_ASSOC));
    }

    public function responseAffiliation(int $accountId, string $affiliationPublicId, bool $forUpdate = false): ?array
    {
        $sql = "SELECT a.id,BIN_TO_UUID(a.public_id) AS public_id,a.affiliation_code,a.workspace_id,a.organization_id,a.person_id,a.status,a.version,a.request_expires_at,a.requested_by_account_id,BIN_TO_UUID(w.public_id) AS workspace_public_id FROM organization_affiliations a INNER JOIN people_persons p ON p.id=a.person_id AND p.status='ACTIVE' INNER JOIN workspaces w ON w.id=a.workspace_id AND w.status='ACTIVE' WHERE a.public_id=UUID_TO_BIN(:public_id) AND (EXISTS (SELECT 1 FROM people_account_links l WHERE l.account_id=:self_account_id AND l.person_id=a.person_id AND l.link_type='SELF' AND l.status='ACTIVE') OR EXISTS (SELECT 1 FROM people_account_links l INNER JOIN people_role_profiles r ON r.person_id=l.person_id AND r.role_type='GUARDIAN' AND r.status='ACTIVE' INNER JOIN people_guardianships g ON g.guardian_person_id=l.person_id AND g.dependent_person_id=a.person_id AND g.authority_scope='PROFILE_MANAGEMENT' AND g.status='ACTIVE' WHERE l.account_id=:guardian_account_id AND l.link_type='SELF' AND l.status='ACTIVE')) LIMIT 1";
        $statement = $this->db()->prepare($sql . ($forUpdate ? ' FOR UPDATE' : ''));
        $statement->execute(['public_id' => $affiliationPublicId,'self_account_id' => $accountId,'guardian_account_id' => $accountId]);
        return $this->row($statement->fetch(PDO::FETCH_ASSOC));
    }

    public function personHasRequiredRole(int $personInternalId, string $roleType): bool
    {
        $statement = $this->db()->prepare("SELECT 1 FROM people_role_profiles WHERE person_id=:person_id AND role_type=:role_type AND status='ACTIVE' LIMIT 1");
        $statement->execute(['person_id' => $personInternalId,'role_type' => $roleType]);
        return $statement->fetchColumn() !== false;
    }

    public function createRequest(TenantContext $tenant, array $organization, array $candidate, int $actorAccountId, string $publicId, string $code, DateTimeImmutable $expiresAt, array $roles, array $units, DateTimeImmutable $now): array
    {
        $open = $this->openAffiliation($tenant, $organization, (int)$candidate['id'], true);
        if ($open !== null) {
            throw new \DomainException('An open affiliation already exists.');
        }
        $insert = $this->db()->prepare("INSERT INTO organization_affiliations (public_id,affiliation_code,workspace_id,organization_id,person_id,status,requested_by_account_id,responded_by_account_id,response_authority_type,response_guardianship_id,request_expires_at,requested_at,responded_at,activated_at,suspended_at,ended_at,end_reason_code,version,created_at,updated_at) VALUES (UUID_TO_BIN(:public_id),:affiliation_code,:workspace_id,:organization_id,:person_id,'PENDING_ACCEPTANCE',:requested_by_account_id,NULL,NULL,NULL,:expires_at,:now,NULL,NULL,NULL,NULL,NULL,1,:now,:now)");
        $insert->execute(['public_id' => $publicId,'affiliation_code' => $code,'workspace_id' => $tenant->workspaceInternalId(),'organization_id' => (int)$organization['id'],'person_id' => (int)$candidate['id'],'requested_by_account_id' => $actorAccountId,'expires_at' => self::date($expiresAt),'now' => self::date($now)]);
        $affiliation = $this->openAffiliation($tenant, $organization, (int)$candidate['id'], true);
        if ($affiliation === null) {
            throw new \UnexpectedValueException('Created affiliation is unavailable.');
        }
        $unitRows = $this->activeUnits($tenant, $organization, array_values(array_unique(array_column($units, 'public_id'))));
        $unitsByPublicId = [];
        foreach ($unitRows as $unit) {
            $unitsByPublicId[(string)$unit['public_id']] = $unit;
        }
        foreach ($units as $unit) {
            $found = $unitsByPublicId[$unit['public_id']] ?? null;
            if (!is_array($found)) {
                throw new \DomainException('Affiliation unit is unavailable.');
            }
            $statement = $this->db()->prepare("INSERT INTO organization_affiliation_unit_assignments (public_id,workspace_id,organization_id,affiliation_id,unit_id,is_primary,status,assigned_by_account_id,removed_by_account_id,version,proposed_at,activated_at,removed_at,created_at,updated_at) VALUES (UUID_TO_BIN(:public_id),:workspace_id,:organization_id,:affiliation_id,:unit_id,:is_primary,'PROPOSED',:account_id,NULL,1,:now,NULL,NULL,:now,:now)");
            $statement->execute(['public_id' => UuidV7::generate()->toString(),'workspace_id' => $tenant->workspaceInternalId(),'organization_id' => (int)$organization['id'],'affiliation_id' => (int)$affiliation['id'],'unit_id' => (int)$found['id'],'is_primary' => $unit['is_primary'] ? 1 : 0,'account_id' => $actorAccountId,'now' => self::date($now)]);
        }
        $assignedUnits = $this->db()->prepare("SELECT id,BIN_TO_UUID(public_id) AS public_id FROM organization_affiliation_unit_assignments WHERE workspace_id=:workspace_id AND organization_id=:organization_id AND affiliation_id=:affiliation_id AND status='PROPOSED'");
        $assignedUnits->execute(['workspace_id' => $tenant->workspaceInternalId(),'organization_id' => (int)$organization['id'],'affiliation_id' => (int)$affiliation['id']]);
        $assignmentByUnit = [];
        foreach ($this->rows($assignedUnits->fetchAll(PDO::FETCH_ASSOC)) as $unit) {
            $assignmentByUnit[(string)$unit['public_id']] = (int)$unit['id'];
        }
        $definitions = $this->activeRoleDefinitions(array_values(array_unique(array_column($roles, 'code'))));
        $definitionsByCode = [];
        foreach ($definitions as $definition) {
            $definitionsByCode[(string)$definition['code']] = $definition;
        }
        foreach ($roles as $role) {
            $definition = $definitionsByCode[$role['code']] ?? null;
            if (!is_array($definition)) {
                throw new \DomainException('Affiliation role is unavailable.');
            }
            $unitAssignmentId = $role['unit_public_id'] === null ? null : ($assignmentByUnit[$role['unit_public_id']] ?? null);
            if ($role['unit_public_id'] !== null && $unitAssignmentId === null) {
                throw new \DomainException('Role unit scope is unavailable.');
            }
            $statement = $this->db()->prepare("INSERT INTO organization_affiliation_role_assignments (public_id,workspace_id,organization_id,affiliation_id,role_definition_id,unit_assignment_id,display_title,is_primary,status,assigned_by_account_id,removed_by_account_id,version,proposed_at,activated_at,removed_at,created_at,updated_at) VALUES (UUID_TO_BIN(:public_id),:workspace_id,:organization_id,:affiliation_id,:definition_id,:unit_assignment_id,:display_title,:is_primary,'PROPOSED',:account_id,NULL,1,:now,NULL,NULL,:now,:now)");
            $statement->execute(['public_id' => UuidV7::generate()->toString(),'workspace_id' => $tenant->workspaceInternalId(),'organization_id' => (int)$organization['id'],'affiliation_id' => (int)$affiliation['id'],'definition_id' => (int)$definition['id'],'unit_assignment_id' => $unitAssignmentId,'display_title' => $role['title'],'is_primary' => $role['is_primary'] ? 1 : 0,'account_id' => $actorAccountId,'now' => self::date($now)]);
        }
        $this->statusEvent($affiliation, null, 'PENDING_ACCEPTANCE', 'ORGANIZATION_MANAGER', $actorAccountId, null, 'AFFILIATION_REQUESTED', null, $now);
        return $affiliation;
    }

    public function personAffiliation(int $accountId, string $affiliationPublicId, bool $forUpdate = false): ?array
    {
        $sql = "SELECT a.id,BIN_TO_UUID(a.public_id) AS public_id,a.affiliation_code,a.workspace_id,a.organization_id,a.person_id,a.status,a.version,a.request_expires_at,a.requested_by_account_id,BIN_TO_UUID(w.public_id) AS workspace_public_id FROM organization_affiliations a INNER JOIN people_persons p ON p.id=a.person_id AND p.status='ACTIVE' INNER JOIN workspaces w ON w.id=a.workspace_id AND w.status='ACTIVE' WHERE a.public_id=UUID_TO_BIN(:public_id) AND EXISTS (SELECT 1 FROM people_account_links l WHERE l.account_id=:account_id AND l.person_id=p.id AND l.link_type='SELF' AND l.status='ACTIVE') LIMIT 1";
        $statement = $this->db()->prepare($sql . ($forUpdate ? ' FOR UPDATE' : ''));
        $statement->execute(['public_id' => $affiliationPublicId,'account_id' => $accountId]);
        return $this->row($statement->fetch(PDO::FETCH_ASSOC));
    }

    public function accountAffiliations(int $accountId, ?int $managedPersonId = null): array
    {
        $sql = "SELECT BIN_TO_UUID(a.public_id) AS public_id,a.affiliation_code,a.status,a.version,BIN_TO_UUID(o.public_id) AS organization_public_id,n.display_name AS organization_name FROM organization_affiliations a INNER JOIN organizations o ON o.id=a.organization_id AND o.workspace_id=a.workspace_id INNER JOIN organization_names n ON n.organization_id=o.id AND n.workspace_id=o.workspace_id AND n.name_type='PRIMARY' AND n.status='ACTIVE' WHERE (EXISTS (SELECT 1 FROM people_account_links l WHERE l.account_id=:self_account_id AND l.person_id=a.person_id AND l.link_type='SELF' AND l.status='ACTIVE') OR EXISTS (SELECT 1 FROM people_account_links l INNER JOIN people_role_profiles r ON r.person_id=l.person_id AND r.role_type='GUARDIAN' AND r.status='ACTIVE' INNER JOIN people_guardianships g ON g.guardian_person_id=l.person_id AND g.dependent_person_id=a.person_id AND g.authority_scope='PROFILE_MANAGEMENT' AND g.status='ACTIVE' WHERE l.account_id=:guardian_account_id AND l.link_type='SELF' AND l.status='ACTIVE'))";
        $params = ['self_account_id' => $accountId, 'guardian_account_id' => $accountId];
        if ($managedPersonId !== null) {
            $sql .= ' AND a.person_id=:person_id';
            $params['person_id'] = $managedPersonId;
        }
        $sql .= ' ORDER BY a.created_at DESC,a.id DESC LIMIT 100';
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);
        return $this->rows($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function roster(TenantContext $tenant, array $organization, string $status, int $limit): array
    {
        $sql = "SELECT BIN_TO_UUID(a.public_id) AS public_id,a.affiliation_code,a.status,a.version,a.created_at,BIN_TO_UUID(p.public_id) AS person_public_id,pn.display_name,rd.code AS primary_role FROM organization_affiliations a LEFT JOIN people_persons p ON p.id=a.person_id LEFT JOIN people_person_names pn ON pn.person_id=p.id AND pn.name_type='PRIMARY' AND pn.status='ACTIVE' LEFT JOIN organization_affiliation_role_assignments ra ON ra.affiliation_id=a.id AND ra.workspace_id=a.workspace_id AND ra.organization_id=a.organization_id AND ra.status IN ('PROPOSED','ACTIVE') AND ra.is_primary=1 LEFT JOIN organization_affiliation_role_definitions rd ON rd.id=ra.role_definition_id WHERE a.workspace_id=:workspace_id AND a.organization_id=:organization_id";
        $params = ['workspace_id' => $tenant->workspaceInternalId(),'organization_id' => (int)$organization['id'],'limit' => $limit];
        if ($status !== 'ALL') {
            $sql .= ' AND a.status=:status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY a.created_at DESC,a.id DESC LIMIT :limit';
        $statement = $this->db()->prepare($sql);
        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $key, $value, $key === 'limit' ? PDO::PARAM_INT : PDO::PARAM_STR);
        } $statement->execute();
        $rows = $this->rows($statement->fetchAll(PDO::FETCH_ASSOC));
        foreach ($rows as &$row) {
            if (!in_array($row['status'], ['ACTIVE','SUSPENDED','ENDED'], true)) {
                unset($row['person_public_id'], $row['display_name']);
            }
        }
        return $rows;
    }

    public function assignments(TenantContext $tenant, array $affiliation): array
    {
        $statement = $this->db()->prepare("SELECT BIN_TO_UUID(ra.public_id) AS public_id,rd.code,rd.sensitivity_level,rd.required_person_role_type,ra.display_title,ra.is_primary,ra.status,BIN_TO_UUID(ua.public_id) AS unit_assignment_public_id FROM organization_affiliation_role_assignments ra INNER JOIN organization_affiliation_role_definitions rd ON rd.id=ra.role_definition_id LEFT JOIN organization_affiliation_unit_assignments ua ON ua.id=ra.unit_assignment_id WHERE ra.workspace_id=:workspace_id AND ra.organization_id=:organization_id AND ra.affiliation_id=:affiliation_id ORDER BY ra.created_at,ra.id");
        $statement->execute(['workspace_id' => $tenant->workspaceInternalId(),'organization_id' => (int)$affiliation['organization_id'],'affiliation_id' => (int)$affiliation['id']]);
        return $this->rows($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function responseAuthority(int $accountId, int $personId): ?array
    {
        $self = $this->db()->prepare("SELECT 'SELF' AS authority,NULL AS guardianship_id FROM people_account_links l INNER JOIN people_persons p ON p.id=l.person_id AND p.status='ACTIVE' INNER JOIN user_accounts a ON a.id=l.account_id AND a.account_status='ACTIVE' WHERE l.account_id=:account_id AND l.person_id=:person_id AND l.link_type='SELF' AND l.status='ACTIVE' LIMIT 1");
        $self->execute(['account_id' => $accountId,'person_id' => $personId]);
        $row = $this->responseAuthorityRow($self->fetch(PDO::FETCH_ASSOC));
        if ($row !== null) {
            return $row;
        }
        $guardian = $this->db()->prepare("SELECT 'GUARDIAN' AS authority,g.id AS guardianship_id FROM people_account_links l INNER JOIN people_persons gp ON gp.id=l.person_id AND gp.status='ACTIVE' INNER JOIN people_role_profiles r ON r.person_id=gp.id AND r.role_type='GUARDIAN' AND r.status='ACTIVE' INNER JOIN people_guardianships g ON g.guardian_person_id=gp.id AND g.dependent_person_id=:person_id AND g.authority_scope='PROFILE_MANAGEMENT' AND g.status='ACTIVE' INNER JOIN people_persons p ON p.id=g.dependent_person_id AND p.status='ACTIVE' INNER JOIN user_accounts a ON a.id=l.account_id AND a.account_status='ACTIVE' WHERE l.account_id=:account_id AND l.link_type='SELF' AND l.status='ACTIVE' LIMIT 1");
        $guardian->execute(['account_id' => $accountId,'person_id' => $personId]);
        return $this->responseAuthorityRow($guardian->fetch(PDO::FETCH_ASSOC));
    }

    public function notificationTargets(int $personId): array
    {
        $statement = $this->db()->prepare("SELECT DISTINCT a.id AS account_id,e.id AS email_id,BIN_TO_UUID(a.public_id) AS account_public_id,a.preferred_locale AS locale FROM people_account_links l INNER JOIN user_accounts a ON a.id=l.account_id AND a.account_status='ACTIVE' INNER JOIN account_email_addresses e ON e.user_account_id=a.id AND e.status_code='VERIFIED' WHERE l.person_id=:self_person_id AND l.link_type='SELF' AND l.status='ACTIVE' UNION SELECT DISTINCT a.id,e.id,BIN_TO_UUID(a.public_id),a.preferred_locale FROM people_guardianships g INNER JOIN people_account_links l ON l.person_id=g.guardian_person_id AND l.link_type='SELF' AND l.status='ACTIVE' INNER JOIN people_role_profiles r ON r.person_id=g.guardian_person_id AND r.role_type='GUARDIAN' AND r.status='ACTIVE' INNER JOIN user_accounts a ON a.id=l.account_id AND a.account_status='ACTIVE' INNER JOIN account_email_addresses e ON e.user_account_id=a.id AND e.status_code='VERIFIED' WHERE g.dependent_person_id=:dependent_person_id AND g.authority_scope='PROFILE_MANAGEMENT' AND g.status='ACTIVE'");
        $statement->execute(['self_person_id' => $personId, 'dependent_person_id' => $personId]);
        $targets = [];
        foreach ($this->rows($statement->fetchAll(PDO::FETCH_ASSOC)) as $row) {
            $targets[] = [
                'account_id' => $this->integer($row, 'account_id'),
                'email_id' => $this->integer($row, 'email_id'),
                'account_public_id' => $this->text($row, 'account_public_id'),
                'locale' => $this->text($row, 'locale'),
            ];
        }

        return $targets;
    }

    public function expiredPending(int $limit, DateTimeImmutable $now): array
    {
        $statement = $this->db()->prepare("SELECT a.id,BIN_TO_UUID(a.public_id) AS public_id,a.workspace_id,a.organization_id,a.person_id,a.status,a.version,a.request_expires_at,a.requested_by_account_id,BIN_TO_UUID(w.public_id) AS workspace_public_id FROM organization_affiliations a INNER JOIN workspaces w ON w.id=a.workspace_id WHERE a.status='PENDING_ACCEPTANCE' AND a.request_expires_at<=:now ORDER BY a.request_expires_at,a.id LIMIT :limit FOR UPDATE SKIP LOCKED");
        $statement->bindValue(':now', self::date($now));
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        return $this->rows($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function transition(array $affiliation, string $toStatus, ?int $respondedByAccountId, ?string $responseAuthority, ?int $guardianshipId, string $reasonCode, string $actorAuthority, ?int $actorAccountId, ?int $actorGuardianshipId, ?string $correlationId, DateTimeImmutable $now): array
    {
        $from = (string)$affiliation['status'];
        $allowed = ['PENDING_ACCEPTANCE' => ['ACTIVE','DECLINED','WITHDRAWN','EXPIRED'],'ACTIVE' => ['SUSPENDED','ENDED'],'SUSPENDED' => ['ACTIVE','ENDED']];
        if (!in_array($toStatus, $allowed[$from] ?? [], true)) {
            throw new \DomainException('Affiliation lifecycle transition is invalid.');
        }
        $columns = ["status=:status","version=version+1","updated_at=:now"];
        $params = ['status' => $toStatus,'now' => self::date($now),'id' => (int)$affiliation['id'],'version' => (int)$affiliation['version']];
        if (in_array($toStatus, ['ACTIVE','DECLINED'], true)) {
            $columns[] = 'responded_by_account_id=:responded_by';
            $columns[] = 'response_authority_type=:response_authority';
            $columns[] = 'response_guardianship_id=:response_guardianship';
            $columns[] = 'responded_at=:now';
            $params['responded_by'] = $respondedByAccountId;
            $params['response_authority'] = $responseAuthority;
            $params['response_guardianship'] = $guardianshipId;
        }
        if ($toStatus === 'ACTIVE') {
            $columns[] = 'activated_at=:now';
        }
        if ($toStatus === 'SUSPENDED') {
            $columns[] = 'suspended_at=:now';
        }
        if ($toStatus === 'ENDED') {
            $columns[] = 'ended_at=:now';
            $columns[] = 'end_reason_code=:reason';
            $params['reason'] = $reasonCode;
        }
        $statement = $this->db()->prepare('UPDATE organization_affiliations SET ' . implode(',', $columns) . ' WHERE id=:id AND version=:version AND status=:from_status');
        $params['from_status'] = $from;
        $statement->execute($params);
        if ($statement->rowCount() !== 1) {
            throw new \DomainException('Affiliation is stale or unavailable.');
        }
        $next = $affiliation;
        $next['status'] = $toStatus;
        $next['version'] = (int)$affiliation['version'] + 1;
        $this->statusEvent($next, $from, $toStatus, $actorAuthority, $actorAccountId, $actorGuardianshipId, $reasonCode, $correlationId, $now);
        return $next;
    }

    public function activateProposedAssignments(array $affiliation, DateTimeImmutable $now): void
    {
        $this->assignmentStatus($affiliation, 'PROPOSED', 'ACTIVE', null, $now);
    }
    public function cancelProposedAssignments(array $affiliation, DateTimeImmutable $now): void
    {
        $this->assignmentStatus($affiliation, 'PROPOSED', 'CANCELLED', null, $now);
    }
    public function removeActiveAssignments(array $affiliation, int $actorAccountId, DateTimeImmutable $now): void
    {
        $this->assignmentStatus($affiliation, 'ACTIVE', 'REMOVED', $actorAccountId, $now);
    }

    public function replaceAssignments(TenantContext $tenant, array $organization, array $affiliation, int $actorAccountId, array $roles, array $units, DateTimeImmutable $now): void
    {
        $this->removeActiveAssignments($affiliation, $actorAccountId, $now);
        $unitRows = $this->activeUnits($tenant, $organization, array_values(array_unique(array_column($units, 'public_id'))));
        $unitByPublic = [];
        foreach ($unitRows as $unit) {
            $unitByPublic[(string)$unit['public_id']] = $unit;
        }
        foreach ($units as $unit) {
            $found = $unitByPublic[$unit['public_id']] ?? null;
            if (!is_array($found)) {
                throw new \DomainException('Affiliation unit is unavailable.');
            }
            $statement = $this->db()->prepare("INSERT INTO organization_affiliation_unit_assignments (public_id,workspace_id,organization_id,affiliation_id,unit_id,is_primary,status,assigned_by_account_id,removed_by_account_id,version,proposed_at,activated_at,removed_at,created_at,updated_at) VALUES (UUID_TO_BIN(:public_id),:workspace_id,:organization_id,:affiliation_id,:unit_id,:is_primary,'ACTIVE',:account_id,NULL,1,NULL,:now,NULL,:now,:now)");
            $statement->execute(['public_id' => UuidV7::generate()->toString(),'workspace_id' => $tenant->workspaceInternalId(),'organization_id' => (int)$organization['id'],'affiliation_id' => (int)$affiliation['id'],'unit_id' => (int)$found['id'],'is_primary' => $unit['is_primary'] ? 1 : 0,'account_id' => $actorAccountId,'now' => self::date($now)]);
        }
        $select = $this->db()->prepare("SELECT ua.id,u.public_id AS unit_public_id FROM organization_affiliation_unit_assignments ua INNER JOIN organization_units u ON u.id=ua.unit_id WHERE ua.workspace_id=:workspace_id AND ua.organization_id=:organization_id AND ua.affiliation_id=:affiliation_id AND ua.status='ACTIVE'");
        $select->execute(['workspace_id' => $tenant->workspaceInternalId(),'organization_id' => (int)$organization['id'],'affiliation_id' => (int)$affiliation['id']]);
        $assignmentByUnit = [];
        foreach ($this->rows($select->fetchAll(PDO::FETCH_ASSOC)) as $unit) {
            $assignmentByUnit[UuidV7::fromBinary((string)$unit['unit_public_id'])->toString()] = (int)$unit['id'];
        }
        $definitions = [];
        foreach ($this->activeRoleDefinitions(array_values(array_unique(array_column($roles, 'code')))) as $definition) {
            $definitions[(string)$definition['code']] = $definition;
        }
        foreach ($roles as $role) {
            $definition = $definitions[$role['code']] ?? null;
            if (!is_array($definition)) {
                throw new \DomainException('Affiliation role is unavailable.');
            }$unitAssignmentId = $role['unit_public_id'] === null ? null : ($assignmentByUnit[$role['unit_public_id']] ?? null);
            if ($role['unit_public_id'] !== null && $unitAssignmentId === null) {
                throw new \DomainException('Role unit scope is unavailable.');
            }$statement = $this->db()->prepare("INSERT INTO organization_affiliation_role_assignments (public_id,workspace_id,organization_id,affiliation_id,role_definition_id,unit_assignment_id,display_title,is_primary,status,assigned_by_account_id,removed_by_account_id,version,proposed_at,activated_at,removed_at,created_at,updated_at) VALUES (UUID_TO_BIN(:public_id),:workspace_id,:organization_id,:affiliation_id,:definition_id,:unit_assignment_id,:display_title,:is_primary,'ACTIVE',:account_id,NULL,1,NULL,:now,NULL,:now,:now)");
            $statement->execute(['public_id' => UuidV7::generate()->toString(),'workspace_id' => $tenant->workspaceInternalId(),'organization_id' => (int)$organization['id'],'affiliation_id' => (int)$affiliation['id'],'definition_id' => (int)$definition['id'],'unit_assignment_id' => $unitAssignmentId,'display_title' => $role['title'],'is_primary' => $role['is_primary'] ? 1 : 0,'account_id' => $actorAccountId,'now' => self::date($now)]);
        }
    }

    public function incrementVersion(array $affiliation, DateTimeImmutable $now): array
    {
        $statement = $this->db()->prepare(
            'UPDATE organization_affiliations SET version = version + 1, updated_at = :now '
            . 'WHERE id = :id AND workspace_id = :workspace_id AND organization_id = :organization_id '
            . 'AND version = :version AND status = :status',
        );
        $statement->execute([
            'now' => self::date($now),
            'id' => (int) $affiliation['id'],
            'workspace_id' => (int) $affiliation['workspace_id'],
            'organization_id' => (int) $affiliation['organization_id'],
            'version' => (int) $affiliation['version'],
            'status' => (string) $affiliation['status'],
        ]);
        if ($statement->rowCount() !== 1) {
            throw new \DomainException('Affiliation is stale or unavailable.');
        }

        $next = $affiliation;
        $next['version'] = (int) $affiliation['version'] + 1;

        return $next;
    }

    public function report(): array
    {
        $counts = [];
        foreach (['role_definitions' => "SELECT COUNT(*) FROM organization_affiliation_role_definitions WHERE status='ACTIVE'",'pending' => "SELECT COUNT(*) FROM organization_affiliations WHERE status='PENDING_ACCEPTANCE'",'active' => "SELECT COUNT(*) FROM organization_affiliations WHERE status='ACTIVE'",'suspended' => "SELECT COUNT(*) FROM organization_affiliations WHERE status='SUSPENDED'",'ended' => "SELECT COUNT(*) FROM organization_affiliations WHERE status='ENDED'"] as $key => $sql) {
            $counts[$key] = $this->count($sql);
        }
        $counts['invalid_rows'] = $this->count("SELECT COUNT(*) FROM organization_affiliations a WHERE (a.status='PENDING_ACCEPTANCE' AND (SELECT COUNT(*) FROM organization_affiliation_role_assignments r WHERE r.affiliation_id=a.id AND r.status='PROPOSED')=0) OR (a.status IN ('ACTIVE','SUSPENDED') AND ((SELECT COUNT(*) FROM organization_affiliation_role_assignments r WHERE r.affiliation_id=a.id AND r.status='ACTIVE')=0 OR (SELECT COUNT(*) FROM organization_affiliation_role_assignments r WHERE r.affiliation_id=a.id AND r.status='ACTIVE' AND r.is_primary=1)<>1))");
        return $counts;
    }

    public function organizationHasOpenAffiliations(int $workspaceId, int $organizationId): bool
    {
        $s = $this->db()->prepare("SELECT 1 FROM organization_affiliations WHERE workspace_id=:workspace_id AND organization_id=:organization_id AND status IN ('PENDING_ACCEPTANCE','ACTIVE','SUSPENDED') LIMIT 1");
        $s->execute(['workspace_id' => $workspaceId,'organization_id' => $organizationId]);
        return $s->fetchColumn() !== false;
    }
    public function unitHasOpenAssignments(int $workspaceId, int $organizationId, int $unitId): bool
    {
        $s = $this->db()->prepare("SELECT 1 FROM organization_affiliation_unit_assignments WHERE workspace_id=:workspace_id AND organization_id=:organization_id AND unit_id=:unit_id AND status IN ('PROPOSED','ACTIVE') LIMIT 1");
        $s->execute(['workspace_id' => $workspaceId,'organization_id' => $organizationId,'unit_id' => $unitId]);
        return $s->fetchColumn() !== false;
    }

    /** @param Row $affiliation */
    private function statusEvent(array $affiliation, ?string $from, string $to, string $authority, ?int $accountId, ?int $guardianshipId, string $reason, ?string $correlationId, DateTimeImmutable $now): void
    {
        $s = $this->db()->prepare('INSERT INTO organization_affiliation_status_events (public_id,workspace_id,organization_id,affiliation_id,from_status,to_status,actor_authority,actor_account_id,guardianship_id,reason_code,correlation_id,occurred_at,created_at) VALUES (UUID_TO_BIN(:public_id),:workspace_id,:organization_id,:affiliation_id,:from_status,:to_status,:authority,:account_id,:guardianship_id,:reason,:correlation_id,:now,:now)');
        $s->execute(['public_id' => UuidV7::generate()->toString(),'workspace_id' => (int)$affiliation['workspace_id'],'organization_id' => (int)$affiliation['organization_id'],'affiliation_id' => (int)$affiliation['id'],'from_status' => $from,'to_status' => $to,'authority' => $authority,'account_id' => $accountId,'guardianship_id' => $guardianshipId,'reason' => $reason,'correlation_id' => $correlationId,'now' => self::date($now)]);
    }
    /** @param Row $affiliation */
    private function assignmentStatus(array $affiliation, string $from, string $to, ?int $actorAccountId, DateTimeImmutable $now): void
    {
        foreach (['organization_affiliation_role_assignments','organization_affiliation_unit_assignments'] as $table) {
            $columns = "status=:to_status,version=version+1,updated_at=:now";
            if ($to === 'ACTIVE') {
                $columns .= ',activated_at=:now';
            } else {
                $columns .= ',removed_at=:now';
                if ($to === 'REMOVED') {
                    $columns .= ',removed_by_account_id=:actor_account_id';
                }
            }$s = $this->db()->prepare("UPDATE $table SET $columns WHERE workspace_id=:workspace_id AND organization_id=:organization_id AND affiliation_id=:affiliation_id AND status=:from_status");
            $s->execute(['to_status' => $to,'now' => self::date($now),'actor_account_id' => $actorAccountId,'workspace_id' => (int)$affiliation['workspace_id'],'organization_id' => (int)$affiliation['organization_id'],'affiliation_id' => (int)$affiliation['id'],'from_status' => $from]);
        }
    }
    private function db(): PDO
    {
        return $this->provider->connection();
    }
    /** @return Row|null */
    private function row(mixed $row): ?array
    {
        if (!is_array($row)) {
            return null;
        }

        $normalized = [];
        foreach ($row as $key => $value) {
            if (!is_string($key) || (!is_int($value) && !is_string($value) && $value !== null)) {
                throw new \UnexpectedValueException('Affiliation query row is invalid.');
            }
            $normalized[$key] = $value;
        }

        return $normalized;
    }

    /** @return list<Row> */
    private function rows(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        $normalized = [];
        foreach ($rows as $row) {
            $value = $this->row($row);
            if ($value === null) {
                throw new \UnexpectedValueException('Affiliation query result is invalid.');
            }
            $normalized[] = $value;
        }

        return $normalized;
    }

    /** @return array{authority: string, guardianship_id: int|null}|null */
    private function responseAuthorityRow(mixed $row): ?array
    {
        $value = $this->row($row);
        if ($value === null) {
            return null;
        }

        return [
            'authority' => $this->text($value, 'authority'),
            'guardianship_id' => $this->nullableInteger($value, 'guardianship_id'),
        ];
    }

    /** @param Row $row */
    private function text(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value)) {
            throw new \UnexpectedValueException('Affiliation query text value is invalid.');
        }

        return $value;
    }

    /** @param Row $row */
    private function integer(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value) || preg_match('/\A-?[0-9]+\z/', $value) !== 1) {
            throw new \UnexpectedValueException('Affiliation query integer value is invalid.');
        }

        return (int) $value;
    }

    /** @param Row $row */
    private function nullableInteger(array $row, string $key): ?int
    {
        if (($row[$key] ?? null) === null) {
            return null;
        }

        return $this->integer($row, $key);
    }

    private function count(string $sql): int
    {
        $statement = $this->db()->query($sql);
        if (!$statement instanceof PDOStatement) {
            throw new \RuntimeException('Affiliation count query cannot be prepared.');
        }

        $value = $statement->fetchColumn();
        if (!is_int($value) && !is_string($value)) {
            throw new \UnexpectedValueException('Affiliation count is invalid.');
        }

        return (int) $value;
    }
    private static function date(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }
}
