<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;

final readonly class MediaModerationModule implements Module
{
    public function id(): ModuleId { return new ModuleId('media.moderation'); }
    public function dependencies(): array { return [new ModuleId('media.catalog'), new ModuleId('security.authorization'), new ModuleId('security.audit')]; }
    public function register(ModuleRegistrationContext $context): void {}
}
