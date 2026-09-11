<?php

declare(strict_types=1);

namespace Qmdb\Modules\QuranReferenceGovernance\Domain;

use DateTimeImmutable;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationCatalogBuilder;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationScopeType;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionCode;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionDefinition;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionId;
use Qmdb\Modules\SecurityAuthorization\Domain\PermissionStatus;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleCode;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleDefinition;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleId;
use Qmdb\Modules\SecurityAuthorization\Domain\RoleStatus;

final class QuranReferenceGovernanceAuthorizationCatalog
{
    public static function extend(AuthorizationCatalogBuilder $b): void
    {
        $t = new DateTimeImmutable('2026-09-10T00:00:00.000000Z');
        $permissions = [['01a0474d-c402-7001-8000-000000000001','platform.quran_sources.view','PRIMARY'],['01a0474d-c402-7001-8000-000000000002','platform.quran_releases.view','MULTI_FACTOR'],['01a0474d-c402-7001-8000-000000000003','platform.quran_releases.manage','MULTI_FACTOR'],['01a0474d-c402-7001-8000-000000000004','platform.quran_releases.approve','PHISHING_RESISTANT'],['01a0474d-c402-7001-8000-000000000005','platform.quran_releases.activate','PHISHING_RESISTANT'],['01a0474d-c402-7001-8000-000000000007','platform.quran_search_corpus.view','MULTI_FACTOR'],['01a0474d-c402-7001-8000-000000000008','platform.quran_search_corpus.validate','PHISHING_RESISTANT'],['01a0474d-c402-7001-8000-000000000009','platform.quran_public_reference.verify','MULTI_FACTOR']];
        foreach ($permissions as [$id,$code,$assurance]) {
            $b->permission(new PermissionDefinition(PermissionId::fromString($id), new PermissionCode($code), AuthorizationScopeType::PLATFORM, AuthenticationAssuranceLevel::from($assurance), PermissionStatus::ACTIVE, 'quran.reference_governance', 1, $t, $t));
        }
        $b->role(new RoleDefinition(RoleId::fromString('01a0474d-c402-7001-8000-000000000006'), new RoleCode('platform.quran_governance_steward'), AuthorizationScopeType::PLATFORM, RoleStatus::ACTIVE, true, 1, $t, $t));
        foreach (['platform.security_administrator', 'platform.quran_governance_steward'] as $role) {
            foreach ($permissions as [, $permission]) {
                $b->map(new RoleCode($role), new PermissionCode($permission));
            }
        }
        foreach (['platform.quran_sources.view', 'platform.quran_releases.view', 'platform.quran_search_corpus.view', 'platform.quran_public_reference.verify'] as $permission) {
            $b->map(new RoleCode('platform.authorization_auditor'), new PermissionCode($permission));
        }
    }
}
