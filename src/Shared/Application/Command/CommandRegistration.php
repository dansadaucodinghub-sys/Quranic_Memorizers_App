<?php

declare(strict_types=1);

namespace Qmdb\Shared\Application\Command;

final readonly class CommandRegistration
{
    public function __construct(
        public string $commandClass,
        public string $handlerServiceId,
        public string $ownerModuleId,
    ) {
    }
}
