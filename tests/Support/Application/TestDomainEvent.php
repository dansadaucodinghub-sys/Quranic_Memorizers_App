<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Application;

use DateTimeImmutable;
use DateTimeZone;
use Qmdb\Shared\Domain\Event\DomainEvent;
use Qmdb\Shared\Domain\Event\DomainEventName;
use Qmdb\Shared\Identifier\RuntimeIdentifier;

final readonly class TestDomainEvent implements DomainEvent
{
    public function __construct(
        private RuntimeIdentifier $id,
        private DomainEventName $name,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    public static function create(): self
    {
        return new self(
            RuntimeIdentifier::fromString('0123456789abcdef0123456789abcdef'),
            new DomainEventName('system.runtime.started'),
            new DateTimeImmutable('2026-08-25 12:00:00', new DateTimeZone('UTC')),
        );
    }

    public function eventId(): RuntimeIdentifier
    {
        return $this->id;
    }

    public function eventName(): DomainEventName
    {
        return $this->name;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
