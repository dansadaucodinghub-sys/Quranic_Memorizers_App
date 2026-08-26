<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Application;

use Qmdb\Shared\Application\Command\Command;

final readonly class TestCommand implements Command
{
    public function __construct(public string $value)
    {
    }
}
