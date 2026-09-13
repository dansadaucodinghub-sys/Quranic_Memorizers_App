<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Module;

use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;

/** P7 review ownership; P6 remains the only base appeal aggregate. */
final readonly class CompetitionAppealAdjudicationModule implements Module
{
    public function id(): ModuleId
    {
        return new ModuleId('competition.appeal_adjudication');
    }

    public function dependencies(): array
    {
        return [
            new ModuleId('competition.results'),
            new ModuleId('competition.result_publication'),
            new ModuleId('competition.scoring'),
            new ModuleId('competition.registration'),
            new ModuleId('foundation.database'),
            new ModuleId('security.authorization'),
            new ModuleId('security.audit'),
            new ModuleId('security.web'),
            new ModuleId('tenancy.context'),
        ];
    }

    public function register(ModuleRegistrationContext $context): void
    {
    }
}
