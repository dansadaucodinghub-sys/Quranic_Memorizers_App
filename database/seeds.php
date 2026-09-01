<?php

declare(strict_types=1);

use Qmdb\Modules\SecurityAuthorization\Infrastructure\Seed\SeedFoundationalAuthorizationCatalog;
use Qmdb\Modules\SecurityPrivilegedAccess\Infrastructure\Seed\SeedPrivilegedAccessCatalog;
use Qmdb\Modules\IdentityAccountState\Infrastructure\Seed\SeedAccountStateAuthorizationCatalog;
use Qmdb\Modules\Geography\Infrastructure\Seed\SeedNigeriaAdministrativeGeography;
use Qmdb\Modules\Organizations\Infrastructure\Seed\SeedOrganizationCatalogAndAuthorization;
use Qmdb\Shared\Schema\Seed\SeedRegistry;
use Qmdb\Shared\Schema\Seed\SeedRegistryBuilder;

return static function (): SeedRegistry {
    return (new SeedRegistryBuilder())
        ->register(new SeedFoundationalAuthorizationCatalog())
        ->register(new SeedPrivilegedAccessCatalog())
        ->register(new SeedAccountStateAuthorizationCatalog())
        ->register(new SeedNigeriaAdministrativeGeography())
        ->register(new SeedOrganizationCatalogAndAuthorization())
        ->build();
};
