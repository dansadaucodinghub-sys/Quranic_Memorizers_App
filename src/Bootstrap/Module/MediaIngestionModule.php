<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;

final readonly class MediaIngestionModule implements Module
{
    public function id(): ModuleId { return new ModuleId('media.ingestion'); }
    public function dependencies(): array { return [new ModuleId('media.catalog'), new ModuleId('security.web'), new ModuleId('security.audit')]; }
    public function register(ModuleRegistrationContext $context): void {}
}
