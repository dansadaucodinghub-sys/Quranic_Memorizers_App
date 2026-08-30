<?php

declare(strict_types=1);

use Qmdb\Modules\SecurityAuthorization\Infrastructure\Seed\SeedFoundationalAuthorizationCatalog;
use Qmdb\Modules\SecurityPrivilegedAccess\Infrastructure\Seed\SeedPrivilegedAccessCatalog;
use Qmdb\Modules\IdentityAccountState\Infrastructure\Seed\SeedAccountStateAuthorizationCatalog;
use Qmdb\Shared\Schema\Seed\SeedRegistry;
use Qmdb\Shared\Schema\Seed\SeedRegistryBuilder;

return static function (): SeedRegistry {
    return (new SeedRegistryBuilder())
        ->register(new SeedFoundationalAuthorizationCatalog())
        ->register(new SeedPrivilegedAccessCatalog())
        ->register(new SeedAccountStateAuthorizationCatalog())
        ->build();
};
