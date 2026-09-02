<?php

declare(strict_types=1);

namespace Qmdb\Modules\OrganizationAffiliations\Infrastructure\Seed;

use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationCatalogRegistry;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleCode;
use Qmdb\Shared\Schema\Seed\Seed;
use Qmdb\Shared\Schema\Seed\SeedId;
use Qmdb\Shared\Schema\Seed\SeedStepId;
use Qmdb\Shared\Schema\Seed\SqlSeedStep;

final readonly class SeedOrganizationAffiliationAuthorization implements Seed
{
    public function id(): SeedId
    {
        return new SeedId('20260901040400_seed_organization_affiliation_authorization');
    }
    public function description(): string
    {
        return 'Seed Organization affiliation permissions and people-manager role mapping.';
    }
    public function dependencies(): array
    {
        return [new SeedId('20260901040300_seed_organization_affiliation_role_definitions')];
    }

    public function steps(): array
    {
        $catalog = AuthorizationCatalogRegistry::withOrganizationsAffiliations();
        $codes = ['workspace.organization_affiliations.view','workspace.organization_affiliations.manage','workspace.organization_affiliation_assignments.manage','workspace.organization_leadership.manage'];
        $steps = [];
        foreach ($codes as $position => $code) {
            $permission = $catalog->permission(new PermissionCode($code));
            if ($permission === null) {
                throw new \LogicException('Organization affiliation permission catalog is inconsistent.');
            }
            $steps[] = new SqlSeedStep(new SeedStepId(sprintf('%03d_permission', $position + 1)), 'Insert a governed Organization affiliation permission.', 'INSERT INTO authorization_permissions (public_id,code,scope_type,required_assurance_level,status,owning_module,version,created_at,updated_at,retired_at) VALUES (UUID_TO_BIN(:public_id),:code,:scope_type,:assurance,:status,:owner,1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL)', [':public_id' => $permission->id->toString(),':code' => $permission->code->value(),':scope_type' => $permission->scopeType->value,':assurance' => $permission->requiredAssurance->value,':status' => $permission->status->value,':owner' => $permission->owningModule]);
        }
        $role = $catalog->role(new RoleCode('workspace.organization_people_manager'));
        if ($role === null) {
            throw new \LogicException('Organization people-manager role catalog is inconsistent.');
        }
        $steps[] = new SqlSeedStep(new SeedStepId('005_people_manager_role'), 'Insert the Organization people-manager workspace role.', 'INSERT INTO authorization_roles (public_id,code,scope_type,status,is_system,version,created_at,updated_at,retired_at) VALUES (UUID_TO_BIN(:public_id),:code,:scope_type,:status,1,1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL)', [':public_id' => $role->id->toString(),':code' => $role->code->value(),':scope_type' => $role->scopeType->value,':status' => $role->status->value]);
        $position = 6;
        foreach ($catalog->mappings() as $mapping) {
            $roleCode = $mapping->roleCode->value();
            $permissionCode = $mapping->permissionCode->value();
            $needed = ($roleCode === 'workspace.organization_people_manager' && in_array($permissionCode, array_merge($codes, ['workspace.organizations.view','workspace.organization_units.view']), true))
                || (in_array($roleCode, ['workspace.owner','workspace.administrator'], true) && in_array($permissionCode, $codes, true));
            if (!$needed) {
                continue;
            }
            $mappedRole = $catalog->role(new RoleCode($mapping->roleCode->value()));
            $mappedPermission = $catalog->permission(new PermissionCode($mapping->permissionCode->value()));
            if ($mappedRole === null || $mappedPermission === null) {
                throw new \LogicException('Organization affiliation mapping catalog is inconsistent.');
            }
            $steps[] = new SqlSeedStep(new SeedStepId(sprintf('%03d_mapping', $position++)), 'Map a workspace role to a governed Organization affiliation permission.', 'INSERT INTO authorization_role_permissions (role_id,role_scope_type,permission_id,permission_scope_type,created_at) SELECT r.id,r.scope_type,p.id,p.scope_type,UTC_TIMESTAMP(6) FROM authorization_roles r INNER JOIN authorization_permissions p ON p.public_id=UUID_TO_BIN(:permission_id) WHERE r.public_id=UUID_TO_BIN(:role_id)', [':role_id' => $mappedRole->id->toString(),':permission_id' => $mappedPermission->id->toString()]);
        }

        return $steps;
    }
}
