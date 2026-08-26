<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Application;

use Qmdb\Shared\Application\Event\DomainEventHandler;

final readonly class NonCallableEventHandler implements DomainEventHandler
{
}
