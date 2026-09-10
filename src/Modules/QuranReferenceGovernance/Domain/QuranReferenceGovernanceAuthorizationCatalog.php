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
final class QuranReferenceGovernanceAuthorizationCatalog { public static function extend(AuthorizationCatalogBuilder $b): void { $t=new DateTimeImmutable('2026-09-10T00:00:00.000000Z'); foreach([['01a0474d-c402-7001-8000-000000000001','platform.quran_sources.view','PRIMARY'],['01a0474d-c402-7001-8000-000000000002','platform.quran_releases.view','MULTI_FACTOR'],['01a0474d-c402-7001-8000-000000000003','platform.quran_releases.manage','MULTI_FACTOR'],['01a0474d-c402-7001-8000-000000000004','platform.quran_releases.approve','PHISHING_RESISTANT'],['01a0474d-c402-7001-8000-000000000005','platform.quran_releases.activate','PHISHING_RESISTANT']] as [$id,$code,$assurance]) $b->permission(new PermissionDefinition(PermissionId::fromString($id),new PermissionCode($code),AuthorizationScopeType::PLATFORM,AuthenticationAssuranceLevel::from($assurance),PermissionStatus::ACTIVE,'quran.reference_governance',1,$t,$t)); } }
