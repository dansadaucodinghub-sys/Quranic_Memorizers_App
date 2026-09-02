<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityResolution\Infrastructure\Seed;

use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationCatalogRegistry;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleCode;
use Qmdb\Shared\Schema\Seed\Seed;
use Qmdb\Shared\Schema\Seed\SeedId;
use Qmdb\Shared\Schema\Seed\SeedStepId;
use Qmdb\Shared\Schema\Seed\SqlSeedStep;

final readonly class SeedPeopleIdentityResolutionAuthorization implements Seed
{
    public function id(): SeedId
    {
        return new SeedId('20260902050100_seed_people_identity_resolution_authorization');
    }

    public function description(): string
    {
        return 'Seed governed platform profile-claim, verification, and duplicate-review authorization.';
    }

    public function dependencies(): array
    {
        return [new SeedId('20260901040400_seed_organization_affiliation_authorization')];
    }

    public function steps(): array
    {
        $catalog = AuthorizationCatalogRegistry::withPeopleIdentityResolution();
        $codes = ['platform.people_profiles.view', 'platform.people_profile_claims.authorize', 'platform.people_profile_verifications.manage', 'platform.people_duplicates.view', 'platform.people_duplicates.resolve'];
        $steps = [];
        foreach ($codes as $position => $code) {
            $permission = $catalog->permission(new PermissionCode($code));
            if ($permission === null) {
                throw new \LogicException('Identity-resolution permission catalog is inconsistent.');
            }
            $steps[] = new SqlSeedStep(new SeedStepId(sprintf('%03d_permission', $position + 1)), 'Insert a governed identity-resolution platform permission.', 'INSERT INTO authorization_permissions (public_id,code,scope_type,required_assurance_level,status,owning_module,version,created_at,updated_at,retired_at) VALUES (UUID_TO_BIN(:public_id),:code,:scope_type,:assurance,:status,:owner,1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL)', [':public_id' => $permission->id->toString(), ':code' => $permission->code->value(), ':scope_type' => $permission->scopeType->value, ':assurance' => $permission->requiredAssurance->value, ':status' => $permission->status->value, ':owner' => $permission->owningModule]);
        }
        $role = $catalog->role(new RoleCode('platform.people_profile_reviewer'));
        if ($role === null) {
            throw new \LogicException('Identity-resolution role catalog is inconsistent.');
        }
        $steps[] = new SqlSeedStep(new SeedStepId('006_reviewer_role'), 'Insert the identity-resolution platform reviewer role.', 'INSERT INTO authorization_roles (public_id,code,scope_type,status,is_system,version,created_at,updated_at,retired_at) VALUES (UUID_TO_BIN(:public_id),:code,:scope_type,:status,1,1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL)', [':public_id' => $role->id->toString(), ':code' => $role->code->value(), ':scope_type' => $role->scopeType->value, ':status' => $role->status->value]);
        $position = 7;
        foreach ($catalog->mappings() as $mapping) {
            if (!in_array($mapping->roleCode->value(), ['platform.security_administrator', 'platform.people_profile_reviewer', 'platform.authorization_auditor'], true) || !in_array($mapping->permissionCode->value(), $codes, true)) {
                continue;
            }
            $role = $catalog->role($mapping->roleCode);
            $permission = $catalog->permission($mapping->permissionCode);
            if ($role === null || $permission === null) {
                throw new \LogicException('Identity-resolution role mapping catalog is inconsistent.');
            }
            $steps[] = new SqlSeedStep(new SeedStepId(sprintf('%03d_mapping', $position++)), 'Map a governed platform role to an identity-resolution permission.', 'INSERT INTO authorization_role_permissions (role_id,role_scope_type,permission_id,permission_scope_type,created_at) SELECT r.id,r.scope_type,p.id,p.scope_type,UTC_TIMESTAMP(6) FROM authorization_roles r INNER JOIN authorization_permissions p ON p.public_id=UUID_TO_BIN(:permission_id) WHERE r.public_id=UUID_TO_BIN(:role_id)', [':role_id' => $role->id->toString(), ':permission_id' => $permission->id->toString()]);
        }

        return $steps;
    }
}
