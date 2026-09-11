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
        ->build();
};
