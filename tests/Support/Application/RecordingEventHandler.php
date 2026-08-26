<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Application;

use Qmdb\Shared\Application\Event\DomainEventHandler;

final readonly class RecordingEventHandler implements DomainEventHandler
{
    public function __construct(private EventState $state, private string $name)
    {
    }

    public function __invoke(TestDomainEvent $event): void
    {
        $this->state->calls[] = $this->name;
    }
}
