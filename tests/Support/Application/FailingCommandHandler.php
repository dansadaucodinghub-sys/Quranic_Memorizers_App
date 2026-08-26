<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Application;

use Qmdb\Shared\Application\Command\CommandHandler;
use RuntimeException;

final class FailingCommandHandler implements CommandHandler
{
    public int $invocations = 0;

    public function __invoke(TestCommand $command): void
    {
        ++$this->invocations;
        throw new RuntimeException('handler failed');
    }
}
