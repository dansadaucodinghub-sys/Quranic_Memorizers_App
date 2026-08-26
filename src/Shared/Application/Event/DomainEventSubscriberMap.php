<?php

declare(strict_types=1);

namespace Qmdb\Shared\Application\Event;

final readonly class DomainEventSubscriberMap
{
    /** @var array<string, list<DomainEventSubscriberRegistration>> */
    private array $registrations;

    /** @param list<DomainEventSubscriberRegistration> $registrations */
    public function __construct(array $registrations)
    {
        $grouped = [];
        foreach ($registrations as $registration) {
            $grouped[$registration->eventClass][] = $registration;
        }
        $this->registrations = $grouped;
    }

    /** @return list<string> */
    public function subscribersFor(string $eventClass): array
    {
        return array_map(
            static fn (DomainEventSubscriberRegistration $registration): string => $registration->handlerServiceId,
            $this->registrations[$eventClass] ?? [],
        );
    }

    /** @return list<string> */
    public function handlerServiceIds(): array
    {
        $ids = [];
        foreach ($this->registrations as $registrations) {
            foreach ($registrations as $registration) {
                $ids[] = $registration->handlerServiceId;
            }
        }

        return $ids;
    }
}
