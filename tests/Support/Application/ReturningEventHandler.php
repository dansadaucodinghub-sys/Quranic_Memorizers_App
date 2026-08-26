<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Application;

use Qmdb\Shared\Application\Event\DomainEventHandler;
use stdClass;

final readonly class ReturningEventHandler implements DomainEventHandler
{
    public function __invoke(TestDomainEvent $event): object
    {
        return new stdClass();
    }
}
