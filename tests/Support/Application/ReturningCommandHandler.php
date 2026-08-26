<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Application;

use Qmdb\Shared\Application\Command\CommandHandler;
use stdClass;

final readonly class ReturningCommandHandler implements CommandHandler
{
    public function __invoke(TestCommand $command): object
    {
        return new stdClass();
    }
}
