<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Application;

use Qmdb\Shared\Application\Command\CommandHandler;

final readonly class BaseCommandHandler implements CommandHandler
{
    public function __invoke(BaseCommand $command): void
    {
    }
}
