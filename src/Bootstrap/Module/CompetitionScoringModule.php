<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;

final readonly class CompetitionScoringModule implements Module
{
    public function id(): ModuleId { return new ModuleId('competition.scoring'); }
    public function dependencies(): array { return [new ModuleId('competition.judging'), new ModuleId('foundation.database'), new ModuleId('security.authorization'), new ModuleId('security.audit'), new ModuleId('tenancy.context')]; }
    public function register(ModuleRegistrationContext $context): void {}
}
