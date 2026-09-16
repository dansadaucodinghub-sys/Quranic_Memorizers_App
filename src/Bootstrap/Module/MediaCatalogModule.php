<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;

final readonly class MediaCatalogModule implements Module
{
    public function id(): ModuleId { return new ModuleId('media.catalog'); }
    public function dependencies(): array { return [new ModuleId('foundation.database'), new ModuleId('tenancy.context'), new ModuleId('people.profiles')]; }
    public function register(ModuleRegistrationContext $context): void {}
}
