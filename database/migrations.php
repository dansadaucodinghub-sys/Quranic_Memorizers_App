<?php

declare(strict_types=1);

use Qmdb\Shared\Background\Scheduler\Migration\CreateScheduledTaskRunsMigration;
use Qmdb\Modules\Identity\Infrastructure\Migration\CreateAccountSecurityFoundationMigration;
use Qmdb\Modules\Identity\Infrastructure\Migration\CreateUserAccountsMigration;
use Qmdb\Modules\Tenancy\Infrastructure\Migration\CreateWorkspaceMembershipsMigration;
use Qmdb\Modules\Tenancy\Infrastructure\Migration\CreateWorkspacesMigration;
use Qmdb\Shared\Schema\Migration\MigrationRegistry;
use Qmdb\Shared\Schema\Migration\MigrationRegistryBuilder;

return static function (): MigrationRegistry {
    return (new MigrationRegistryBuilder())
        ->register(new CreateScheduledTaskRunsMigration())
        ->register(new CreateWorkspacesMigration())
        ->register(new CreateUserAccountsMigration())
        ->register(new CreateAccountSecurityFoundationMigration())
        ->register(new CreateWorkspaceMembershipsMigration())
        ->build();
};
