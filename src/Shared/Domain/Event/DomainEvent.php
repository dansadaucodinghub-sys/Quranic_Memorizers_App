<?php

declare(strict_types=1);

namespace Qmdb\Shared\Domain\Event;

use DateTimeImmutable;
use Qmdb\Shared\Identifier\RuntimeIdentifier;

interface DomainEvent
{
    public function eventId(): RuntimeIdentifier;

    public function eventName(): DomainEventName;

    public function occurredAt(): DateTimeImmutable;
}
