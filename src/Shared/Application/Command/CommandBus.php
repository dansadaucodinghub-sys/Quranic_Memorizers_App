<?php

declare(strict_types=1);

namespace Qmdb\Shared\Application\Command;

interface CommandBus
{
    public function dispatch(Command $command): void;
}
