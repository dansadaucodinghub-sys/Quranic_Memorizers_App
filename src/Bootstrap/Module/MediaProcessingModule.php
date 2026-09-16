<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;

final readonly class MediaProcessingModule implements Module
{
    public function id(): ModuleId { return new ModuleId('media.processing'); }
    public function dependencies(): array { return [new ModuleId('media.ingestion'), new ModuleId('foundation.application')]; }
    public function register(ModuleRegistrationContext $context): void {}
}
