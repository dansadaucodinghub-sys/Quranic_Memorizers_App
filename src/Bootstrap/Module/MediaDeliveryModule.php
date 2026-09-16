<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;

final readonly class MediaDeliveryModule implements Module
{
    public function id(): ModuleId { return new ModuleId('media.delivery'); }
    public function dependencies(): array { return [new ModuleId('media.moderation'), new ModuleId('foundation.http')]; }
    public function register(ModuleRegistrationContext $context): void {}
}
