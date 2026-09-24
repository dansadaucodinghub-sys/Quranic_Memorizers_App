<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain;

use DateTimeImmutable;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;

/** Exact P8 authorization vocabulary mirrored by the applied P8 seed. */
final readonly class CertificateP8AuthorizationCatalog
{
    /** @var list<array{string,string,string,string}> */
    private const array PERMISSIONS = [
        ['01a08000-0000-7001-8000-000000000001','workspace.certificates.view','WORKSPACE','PHISHING_RESISTANT'],['01a08000-0000-7001-8000-000000000002','workspace.certificates.manage_templates','WORKSPACE','PHISHING_RESISTANT'],['01a08000-0000-7001-8000-000000000003','workspace.certificates.prepare','WORKSPACE','PHISHING_RESISTANT'],['01a08000-0000-7001-8000-000000000004','workspace.certificates.issue','WORKSPACE','PHISHING_RESISTANT'],['01a08000-0000-7001-8000-000000000005','workspace.certificates.revoke','WORKSPACE','PHISHING_RESISTANT'],['01a08000-0000-7001-8000-000000000006','workspace.certificates.supersede','WORKSPACE','PHISHING_RESISTANT'],['01a08000-0000-7001-8000-000000000007','workspace.certificates.archive','WORKSPACE','PHISHING_RESISTANT'],['01a08000-0000-7001-8000-000000000008','workspace.certificates.view_sensitive','WORKSPACE','PHISHING_RESISTANT'],
        ['01a08000-0000-7001-8000-000000000009','workspace.record_passports.view','WORKSPACE','PHISHING_RESISTANT'],['01a08000-0000-7001-8000-000000000010','workspace.record_passports.manage','WORKSPACE','PHISHING_RESISTANT'],['01a08000-0000-7001-8000-000000000011','workspace.trusted_archive.view','WORKSPACE','PHISHING_RESISTANT'],['01a08000-0000-7001-8000-000000000012','workspace.trusted_archive.verify','WORKSPACE','PHISHING_RESISTANT'],['01a08000-0000-7001-8000-000000000013','workspace.trusted_archive.seal','WORKSPACE','PHISHING_RESISTANT'],['01a08000-0000-7001-8000-000000000014','workspace.trusted_archive.manage_holds','WORKSPACE','PHISHING_RESISTANT'],['01a08000-0000-7001-8000-000000000015','workspace.legacy_records.import','WORKSPACE','PHISHING_RESISTANT'],['01a08000-0000-7001-8000-000000000016','workspace.legacy_records.approve','WORKSPACE','PHISHING_RESISTANT'],
        ['01a08000-0000-7001-8000-000000000017','platform.certificate_signing_keys.view','PLATFORM','PHISHING_RESISTANT'],['01a08000-0000-7001-8000-000000000018','platform.certificate_signing_keys.manage','PLATFORM','PHISHING_RESISTANT'],
    ];
    /** @var list<array{string,string,string}> */
    private const array ROLES = [
        ['01a08000-0000-7001-8000-000000000021','workspace.certificate_manager','WORKSPACE'],['01a08000-0000-7001-8000-000000000022','workspace.certificate_issuer','WORKSPACE'],['01a08000-0000-7001-8000-000000000023','workspace.record_passport_manager','WORKSPACE'],['01a08000-0000-7001-8000-000000000024','workspace.archive_auditor','WORKSPACE'],['01a08000-0000-7001-8000-000000000025','workspace.legacy_record_importer','WORKSPACE'],['01a08000-0000-7001-8000-000000000026','platform.certificate_key_custodian','PLATFORM'],
    ];
    public static function extend(AuthorizationCatalogBuilder $builder): void
    {
        $time = new DateTimeImmutable('2026-09-15T00:00:00.000000Z');
        foreach (self::PERMISSIONS as [$id,$code,$scope,$assurance]) {
            $builder->permission(new PermissionDefinition(PermissionId::fromString($id), new PermissionCode($code), AuthorizationScopeType::from($scope), AuthenticationAssuranceLevel::from($assurance), PermissionStatus::ACTIVE, 'certificate.issuance', 1, $time, $time));
        }
        foreach (self::ROLES as [$id,$code,$scope]) {
            $builder->role(new RoleDefinition(RoleId::fromString($id), new RoleCode($code), AuthorizationScopeType::from($scope), RoleStatus::ACTIVE, true, 1, $time, $time));
        }
        $mappings = ['workspace.certificate_manager' => ['workspace.certificates.view','workspace.certificates.manage_templates','workspace.certificates.prepare'],'workspace.certificate_issuer' => ['workspace.certificates.view','workspace.certificates.prepare','workspace.certificates.issue','workspace.certificates.revoke','workspace.certificates.supersede','workspace.certificates.archive'],'workspace.record_passport_manager' => ['workspace.record_passports.view','workspace.record_passports.manage'],'workspace.archive_auditor' => ['workspace.trusted_archive.view','workspace.trusted_archive.verify'],'workspace.legacy_record_importer' => ['workspace.legacy_records.import'],'platform.certificate_key_custodian' => ['platform.certificate_signing_keys.view','platform.certificate_signing_keys.manage']];
        foreach ($mappings as $role => $permissions) {
            foreach ($permissions as $permission) {
                $builder->map(new RoleCode($role), new PermissionCode($permission));
            }
        }
    }
}
