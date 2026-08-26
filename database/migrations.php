<?php

declare(strict_types=1);

use Qmdb\Shared\Background\Scheduler\Migration\CreateScheduledTaskRunsMigration;
use Qmdb\Shared\Schema\Migration\MigrationRegistry;
use Qmdb\Shared\Schema\Migration\MigrationRegistryBuilder;

return static function (): MigrationRegistry {
    return (new MigrationRegistryBuilder())
        ->register(new CreateScheduledTaskRunsMigration())
        ->build();
};
