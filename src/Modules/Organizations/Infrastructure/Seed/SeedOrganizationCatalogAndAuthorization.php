<?php

declare(strict_types=1);

namespace Qmdb\Modules\Organizations\Infrastructure\Seed;

use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationCatalogRegistry;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleCode;
use Qmdb\Shared\Schema\Seed\Seed;
use Qmdb\Shared\Schema\Seed\SeedId;
use Qmdb\Shared\Schema\Seed\SeedStepId;
use Qmdb\Shared\Schema\Seed\SqlSeedStep;

final readonly class SeedOrganizationCatalogAndAuthorization implements Seed
{
    public function id(): SeedId
    {
        return new SeedId('20260901040200_seed_organization_catalog_and_authorization');
    }

    public function description(): string
    {
        return 'Seed fixed Organization classifications and workspace authorization mappings.';
    }

    public function dependencies(): array
    {
        return [new SeedId('20260831020100_seed_nigeria_administrative_geography')];
    }
    public function steps(): array
    {
        $steps = [];
        $catalog = [['01a0474d-b303-7001-8000-000000000001','SCHOOL'],['01a0474d-b303-7001-8000-000000000002','QURANIC_SCHOOL'],['01a0474d-b303-7001-8000-000000000003','MOSQUE'],['01a0474d-b303-7001-8000-000000000004','RECITATION_CENTRE'],['01a0474d-b303-7001-8000-000000000005','GROUP'],['01a0474d-b303-7001-8000-000000000006','ASSOCIATION'],['01a0474d-b303-7001-8000-000000000007','FOUNDATION'],['01a0474d-b303-7001-8000-000000000008','GOVERNMENT_BODY'],['01a0474d-b303-7001-8000-000000000009','COMMUNITY_ORGANIZATION'],['01a0474d-b303-7001-8000-000000000010','OTHER']];
        foreach ($catalog as $i => [$id,$code]) {
            $steps[] = new SqlSeedStep(new SeedStepId(sprintf('%03d_classification', $i + 1)), 'Insert a governed Organization classification.', 'INSERT INTO organization_classifications (public_id,code,status,sort_order,version,created_at,updated_at,retired_at) VALUES (UUID_TO_BIN(:public_id),:code,\'ACTIVE\',:sort_order,1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL)', [':public_id' => $id,':code' => $code,':sort_order' => $i]);
        }
        $authorization = AuthorizationCatalogRegistry::withOrganizationsRegistry();
        $codes = ['workspace.organizations.view','workspace.organizations.manage','workspace.organization_units.view','workspace.organization_units.manage'];
        $position = 11;
        foreach ($codes as $code) {
            $permission = $authorization->permission(new PermissionCode($code));
            if ($permission === null) {
                throw new \LogicException('Organization permission catalog is inconsistent.');
            }$steps[] = new SqlSeedStep(new SeedStepId(sprintf('%03d_permission', $position++)), 'Insert a governed Organization permission.', 'INSERT INTO authorization_permissions (public_id,code,scope_type,required_assurance_level,status,owning_module,version,created_at,updated_at,retired_at) VALUES (UUID_TO_BIN(:public_id),:code,:scope_type,:assurance,:status,:owner,1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL)', [':public_id' => $permission->id->toString(),':code' => $permission->code->value(),':scope_type' => $permission->scopeType->value,':assurance' => $permission->requiredAssurance->value,':status' => $permission->status->value,':owner' => $permission->owningModule]);
        }
        foreach ($authorization->mappings() as $mapping) {
            if (!in_array($mapping->permissionCode->value(), $codes, true) || $mapping->roleCode->value() === 'workspace.organization_people_manager') {
                continue;
            }$role = $authorization->role(new RoleCode($mapping->roleCode->value()));
            $permission = $authorization->permission(new PermissionCode($mapping->permissionCode->value()));
            if ($role === null || $permission === null) {
                throw new \LogicException('Organization role mapping catalog is inconsistent.');
            }$steps[] = new SqlSeedStep(new SeedStepId(sprintf('%03d_mapping', $position++)), 'Map a workspace role to a governed Organization permission.', 'INSERT INTO authorization_role_permissions (role_id,role_scope_type,permission_id,permission_scope_type,created_at) SELECT r.id,r.scope_type,p.id,p.scope_type,UTC_TIMESTAMP(6) FROM authorization_roles r INNER JOIN authorization_permissions p ON p.public_id=UUID_TO_BIN(:permission_id) WHERE r.public_id=UUID_TO_BIN(:role_id)', [':role_id' => $role->id->toString(),':permission_id' => $permission->id->toString()]);
        }
        return $steps;
    }
}
