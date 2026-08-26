<?php

declare(strict_types=1);

namespace Qmdb\Shared\Application\Event;

use Qmdb\Shared\Domain\Event\DomainEvent;

interface DomainEventDispatcher
{
    public function dispatch(DomainEvent $event): void;
}
