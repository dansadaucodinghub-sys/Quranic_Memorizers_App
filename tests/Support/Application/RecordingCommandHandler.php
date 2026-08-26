<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Application;

use Qmdb\Shared\Application\Command\CommandHandler;

final class RecordingCommandHandler implements CommandHandler
{
    public int $invocations = 0;
    public ?TestCommand $received = null;

    public function __invoke(TestCommand $command): void
    {
        ++$this->invocations;
        $this->received = $command;
    }
}
