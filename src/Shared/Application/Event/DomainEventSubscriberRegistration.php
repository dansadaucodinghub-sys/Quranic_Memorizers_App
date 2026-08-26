<?php

declare(strict_types=1);

namespace Qmdb\Shared\Application\Event;

final readonly class DomainEventSubscriberRegistration
{
    public function __construct(
        public string $eventClass,
        public string $handlerServiceId,
        public string $ownerModuleId,
    ) {
    }
}
