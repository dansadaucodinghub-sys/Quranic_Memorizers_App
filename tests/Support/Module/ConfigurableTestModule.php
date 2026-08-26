<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Module;

use Closure;
use Qmdb\Shared\Module\Module;
use Qmdb\Shared\Module\ModuleId;
use Qmdb\Shared\Module\ModuleRegistrationContext;

abstract class ConfigurableTestModule implements Module
{
    /**
     * @param list<ModuleId> $dependencies
     * @param (Closure(ModuleRegistrationContext): void)|null $register
     */
    public function __construct(
        private readonly ModuleId $moduleId,
        private readonly array $dependencies = [],
        private readonly ?Closure $register = null,
    ) {
    }

    public function id(): ModuleId
    {
        return $this->moduleId;
    }

    public function dependencies(): array
    {
        return $this->dependencies;
    }

    public function register(ModuleRegistrationContext $context): void
    {
        if ($this->register !== null) {
            ($this->register)($context);
        }
    }
}
