<?php

declare(strict_types=1);

use Qmdb\Modules\SecurityAuthorization\Infrastructure\Seed\SeedFoundationalAuthorizationCatalog;
use Qmdb\Modules\SecurityPrivilegedAccess\Infrastructure\Seed\SeedPrivilegedAccessCatalog;
use Qmdb\Modules\IdentityAccountState\Infrastructure\Seed\SeedAccountStateAuthorizationCatalog;
use Qmdb\Modules\Geography\Infrastructure\Seed\SeedNigeriaAdministrativeGeography;
use Qmdb\Modules\Organizations\Infrastructure\Seed\SeedOrganizationCatalogAndAuthorization;
use Qmdb\Modules\OrganizationAffiliations\Infrastructure\Seed\SeedOrganizationAffiliationRoleDefinitions;
use Qmdb\Modules\OrganizationAffiliations\Infrastructure\Seed\SeedOrganizationAffiliationAuthorization;
use Qmdb\Modules\IdentityResolution\Infrastructure\Seed\SeedPeopleIdentityResolutionAuthorization;
use Qmdb\Modules\QuranReferenceGovernance\Infrastructure\Seed\SeedQuranReferenceSources;
use Qmdb\Modules\QuranReferenceGovernance\Infrastructure\Seed\SeedQuranGovernanceAuthorization;
use Qmdb\Modules\QuranReferenceGovernance\Infrastructure\Seed\SeedQuranSearchCorpusAuthorization;
use Qmdb\Modules\QuranReferenceGovernance\Infrastructure\Seed\SeedCorrectQuranMetadataSourceReference;
use Qmdb\Modules\CompetitionConfiguration\Infrastructure\Seed\SeedCompetitionAuthorizationCatalog;
use Qmdb\Modules\CompetitionConfiguration\Infrastructure\Seed\SeedCompetitionAuthorizationRoleMappings;
use Qmdb\Modules\CompetitionJudging\Infrastructure\Seed\SeedCompetitionP6AuthorizationCatalog;
use Qmdb\Modules\CompetitionJudging\Infrastructure\Seed\SeedCompetitionP6AuthorizationRoleMappings;
use Qmdb\Modules\CompetitionJudging\Infrastructure\Seed\CorrectCompetitionP6AuthorizationCatalog;
use Qmdb\Modules\CompetitionLive\Infrastructure\Seed\SeedCompetitionP7AuthorizationCatalog;
use Qmdb\Modules\CertificateIssuance\Infrastructure\Seed\SeedP8AuthorizationCatalog;
use Qmdb\Modules\MediaCatalog\Infrastructure\Seed\SeedP9MediaAuthorizationCatalog;
use Qmdb\Modules\Community\Infrastructure\Seed\SeedP10CommunityAuthorizationCatalog;
use Qmdb\Shared\Schema\Seed\SeedRegistry;
use Qmdb\Shared\Schema\Seed\SeedRegistryBuilder;

return static function (): SeedRegistry {
    return (new SeedRegistryBuilder())
        ->register(new SeedFoundationalAuthorizationCatalog())
        ->register(new SeedPrivilegedAccessCatalog())
        ->register(new SeedAccountStateAuthorizationCatalog())
        ->register(new SeedNigeriaAdministrativeGeography())
        ->register(new SeedOrganizationCatalogAndAuthorization())
        ->register(new SeedOrganizationAffiliationRoleDefinitions())
        ->register(new SeedOrganizationAffiliationAuthorization())
        ->register(new SeedPeopleIdentityResolutionAuthorization())
        ->register(new SeedQuranReferenceSources())
        ->register(new SeedQuranGovernanceAuthorization())
        ->register(new SeedQuranSearchCorpusAuthorization())
        ->register(new SeedCorrectQuranMetadataSourceReference())
        ->register(new SeedCompetitionAuthorizationCatalog())
        ->register(new SeedCompetitionAuthorizationRoleMappings())
        ->register(new SeedCompetitionP6AuthorizationCatalog())
        ->register(new SeedCompetitionP6AuthorizationRoleMappings())
        ->register(new CorrectCompetitionP6AuthorizationCatalog())
        ->register(new SeedCompetitionP7AuthorizationCatalog())
        ->register(new SeedP8AuthorizationCatalog())
        ->register(new SeedP9MediaAuthorizationCatalog())
        ->register(new SeedP10CommunityAuthorizationCatalog())
        ->build();
};
