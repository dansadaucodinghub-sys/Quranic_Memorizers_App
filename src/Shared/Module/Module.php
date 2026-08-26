<?php

declare(strict_types=1);

namespace Qmdb\Shared\Module;

interface Module
{
    public function id(): ModuleId;

    /** @return list<ModuleId> */
    public function dependencies(): array;

    public function register(ModuleRegistrationContext $context): void;
}
