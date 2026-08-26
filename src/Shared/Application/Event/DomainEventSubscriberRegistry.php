<?php

declare(strict_types=1);

namespace Qmdb\Shared\Application\Event;

use Qmdb\Shared\Application\Exception\DuplicateHandlerRegistrationException;
use Qmdb\Shared\Application\Exception\InvalidMessageHandlerException;
use Qmdb\Shared\Domain\Event\DomainEvent;

final class DomainEventSubscriberRegistry
{
    /** @var list<DomainEventSubscriberRegistration> */
    private array $registrations = [];
    /** @var array<string, true> */
    private array $pairs = [];
    private bool $frozen = false;

    public function register(DomainEventSubscriberRegistration $registration): void
    {
        if ($this->frozen) {
            throw new InvalidMessageHandlerException('Domain-event subscriber registration is closed.');
        }
        if (
            !class_exists($registration->eventClass)
            || !is_subclass_of($registration->eventClass, DomainEvent::class)
        ) {
            throw new InvalidMessageHandlerException('Registered domain-event class is invalid.');
        }
        if (
            $registration->handlerServiceId === ''
            || preg_match('/[\s\x00-\x1F\x7F]/', $registration->handlerServiceId) === 1
        ) {
            throw new InvalidMessageHandlerException('Domain-event handler service identifier is invalid.');
        }

        $pair = $registration->eventClass . '|' . $registration->handlerServiceId;
        if (isset($this->pairs[$pair])) {
            throw new DuplicateHandlerRegistrationException(sprintf(
                'Domain event "%s" already maps to handler "%s".',
                $registration->eventClass,
                $registration->handlerServiceId,
            ));
        }

        $this->pairs[$pair] = true;
        $this->registrations[] = $registration;
    }

    public function freeze(): DomainEventSubscriberMap
    {
        $this->frozen = true;

        return new DomainEventSubscriberMap($this->registrations);
    }
}
