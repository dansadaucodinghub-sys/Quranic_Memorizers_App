<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Application;

use Qmdb\Shared\Application\Command\CommandHandler;

final readonly class NonCallableCommandHandler implements CommandHandler
{
}
