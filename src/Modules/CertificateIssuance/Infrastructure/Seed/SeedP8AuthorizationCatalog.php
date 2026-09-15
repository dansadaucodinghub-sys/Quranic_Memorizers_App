<?php

declare(strict_types=1);

namespace Qmdb\Modules\CertificateIssuance\Infrastructure\Seed;

use Qmdb\Shared\Schema\Seed\Seed;
use Qmdb\Shared\Schema\Seed\SeedId;
use Qmdb\Shared\Schema\Seed\SeedStepId;
use Qmdb\Shared\Schema\Seed\SqlSeedStep;

/** Exact P8 permissions and roles; no account receives a role by seed. */
final readonly class SeedP8AuthorizationCatalog implements Seed
{
    public function id(): SeedId { return new SeedId('20260915105000_seed_p8_authorization_catalog'); }
    public function description(): string { return 'Seed P8 certificates, passport, archive, and legacy import authorization.'; }
    public function dependencies(): array { return [new SeedId('20260912145000_seed_competition_p7_authorization_catalog')]; }
    public function steps(): array
    {
        $permissions = [
            ['01a08000-0000-7001-8000-000000000001', 'workspace.certificates.view', 'WORKSPACE'],
            ['01a08000-0000-7001-8000-000000000002', 'workspace.certificates.manage_templates', 'WORKSPACE'],
            ['01a08000-0000-7001-8000-000000000003', 'workspace.certificates.prepare', 'WORKSPACE'],
            ['01a08000-0000-7001-8000-000000000004', 'workspace.certificates.issue', 'WORKSPACE'],
            ['01a08000-0000-7001-8000-000000000005', 'workspace.certificates.revoke', 'WORKSPACE'],
            ['01a08000-0000-7001-8000-000000000006', 'workspace.certificates.supersede', 'WORKSPACE'],
            ['01a08000-0000-7001-8000-000000000007', 'workspace.certificates.archive', 'WORKSPACE'],
            ['01a08000-0000-7001-8000-000000000008', 'workspace.certificates.view_sensitive', 'WORKSPACE'],
            ['01a08000-0000-7001-8000-000000000009', 'workspace.record_passports.view', 'WORKSPACE'],
            ['01a08000-0000-7001-8000-000000000010', 'workspace.record_passports.manage', 'WORKSPACE'],
            ['01a08000-0000-7001-8000-000000000011', 'workspace.trusted_archive.view', 'WORKSPACE'],
            ['01a08000-0000-7001-8000-000000000012', 'workspace.trusted_archive.verify', 'WORKSPACE'],
            ['01a08000-0000-7001-8000-000000000013', 'workspace.trusted_archive.seal', 'WORKSPACE'],
            ['01a08000-0000-7001-8000-000000000014', 'workspace.trusted_archive.manage_holds', 'WORKSPACE'],
            ['01a08000-0000-7001-8000-000000000015', 'workspace.legacy_records.import', 'WORKSPACE'],
            ['01a08000-0000-7001-8000-000000000016', 'workspace.legacy_records.approve', 'WORKSPACE'],
            ['01a08000-0000-7001-8000-000000000017', 'platform.certificate_signing_keys.view', 'PLATFORM'],
            ['01a08000-0000-7001-8000-000000000018', 'platform.certificate_signing_keys.manage', 'PLATFORM'],
        ];
        $roles = [
            ['01a08000-0000-7001-8000-000000000021', 'workspace.certificate_manager', 'WORKSPACE'],
            ['01a08000-0000-7001-8000-000000000022', 'workspace.certificate_issuer', 'WORKSPACE'],
            ['01a08000-0000-7001-8000-000000000023', 'workspace.record_passport_manager', 'WORKSPACE'],
            ['01a08000-0000-7001-8000-000000000024', 'workspace.archive_auditor', 'WORKSPACE'],
            ['01a08000-0000-7001-8000-000000000025', 'workspace.legacy_record_importer', 'WORKSPACE'],
            ['01a08000-0000-7001-8000-000000000026', 'platform.certificate_key_custodian', 'PLATFORM'],
        ];
        $mappings = [
            'workspace.certificate_manager' => ['workspace.certificates.view', 'workspace.certificates.manage_templates', 'workspace.certificates.prepare'],
            'workspace.certificate_issuer' => ['workspace.certificates.view', 'workspace.certificates.prepare', 'workspace.certificates.issue', 'workspace.certificates.revoke', 'workspace.certificates.supersede', 'workspace.certificates.archive'],
            'workspace.record_passport_manager' => ['workspace.record_passports.view', 'workspace.record_passports.manage'],
            'workspace.archive_auditor' => ['workspace.trusted_archive.view', 'workspace.trusted_archive.verify'],
            'workspace.legacy_record_importer' => ['workspace.legacy_records.import'],
            'platform.certificate_key_custodian' => ['platform.certificate_signing_keys.view', 'platform.certificate_signing_keys.manage'],
        ];
        $steps = [];
        foreach ($permissions as $position => [$id, $code, $scope]) {
            $steps[] = new SqlSeedStep(new SeedStepId(sprintf('%03d_permission', $position + 1)), 'Insert one exact P8 permission.', "INSERT INTO authorization_permissions (public_id,code,scope_type,required_assurance_level,status,owning_module,version,created_at,updated_at,retired_at) VALUES (UUID_TO_BIN(:id),:code,:scope,'PHISHING_RESISTANT','ACTIVE','certificate.issuance',1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL)", [':id' => $id, ':code' => $code, ':scope' => $scope]);
        }
        foreach ($roles as $position => [$id, $code, $scope]) {
            $steps[] = new SqlSeedStep(new SeedStepId(sprintf('%03d_role', $position + 19)), 'Insert one narrow P8 role.', "INSERT INTO authorization_roles (public_id,code,scope_type,status,is_system,version,created_at,updated_at,retired_at) VALUES (UUID_TO_BIN(:id),:code,:scope,'ACTIVE',1,1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),NULL)", [':id' => $id, ':code' => $code, ':scope' => $scope]);
        }
        $sequence = 25;
        foreach ($mappings as $role => $codes) {
            foreach ($codes as $permission) {
                $steps[] = new SqlSeedStep(new SeedStepId(sprintf('%03d_mapping', $sequence++)), 'Map one exact P8 role permission.', "INSERT INTO authorization_role_permissions (role_id,role_scope_type,permission_id,permission_scope_type,created_at) SELECT role_definition.id,role_definition.scope_type,permission_definition.id,permission_definition.scope_type,UTC_TIMESTAMP(6) FROM authorization_roles role_definition INNER JOIN authorization_permissions permission_definition ON permission_definition.code=:permission_code WHERE role_definition.code=:role_code", [':role_code' => $role, ':permission_code' => $permission]);
            }
        }

        return $steps;
    }
}
