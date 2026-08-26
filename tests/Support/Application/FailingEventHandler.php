<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Application;

use Qmdb\Shared\Application\Event\DomainEventHandler;
use RuntimeException;

final readonly class FailingEventHandler implements DomainEventHandler
{
    public function __construct(private EventState $state)
    {
    }

    public function __invoke(TestDomainEvent $event): void
    {
        $this->state->calls[] = 'failure';
        throw new RuntimeException('subscriber failed');
    }
}
