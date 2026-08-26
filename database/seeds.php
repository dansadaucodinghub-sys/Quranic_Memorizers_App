<?php

declare(strict_types=1);

use Qmdb\Shared\Schema\Seed\SeedRegistry;
use Qmdb\Shared\Schema\Seed\SeedRegistryBuilder;

return static function (): SeedRegistry {
    return (new SeedRegistryBuilder())->build();
};
