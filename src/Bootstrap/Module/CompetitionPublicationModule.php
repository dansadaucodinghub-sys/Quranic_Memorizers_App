<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Modules\CompetitionPublication\Domain\ResultPublicationLifecycle;
use Qmdb\Shared\DependencyInjection\ServiceDefinition;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;

/** Result publication is distinct from immutable P6 result calculation. */
final readonly class CompetitionPublicationModule implements Module
{
    public function id(): ModuleId
    {
        return new ModuleId('competition.result_publication');
    }

    public function dependencies(): array
    {
        return [
            new ModuleId('competition.results'),
            new ModuleId('competition.live_operations'),
            new ModuleId('foundation.database'),
            new ModuleId('security.authorization'),
            new ModuleId('security.audit'),
            new ModuleId('security.web'),
            new ModuleId('tenancy.context'),
        ];
    }

    public function register(ModuleRegistrationContext $context): void
    {
        $context->service(ServiceDefinition::instance(
            ResultPublicationLifecycle::class,
            'competition.result_publication',
            new ResultPublicationLifecycle(),
        ));
    }
}
