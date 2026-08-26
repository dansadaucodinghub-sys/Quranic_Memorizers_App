<?php

declare(strict_types=1);

namespace Qmdb\Shared\Application\Event;

use Qmdb\Shared\Application\Exception\InvalidMessageHandlerException;
use Qmdb\Shared\Application\Handler\HandlerResolver;
use Qmdb\Shared\Domain\Event\DomainEvent;

final readonly class SynchronousDomainEventDispatcher implements DomainEventDispatcher
{
    public function __construct(
        private DomainEventSubscriberMap $subscribers,
        private HandlerResolver $handlerResolver,
    ) {
    }

    public function dispatch(DomainEvent $event): void
    {
        foreach ($this->subscribers->subscribersFor($event::class) as $serviceId) {
            $handler = $this->handlerResolver->resolve($serviceId);
            if (!$handler instanceof DomainEventHandler || !is_callable($handler)) {
                throw new InvalidMessageHandlerException(sprintf(
                    'Domain-event handler service "%s" is invalid.',
                    $serviceId,
                ));
            }

            $result = $handler($event);
            if ($result !== null) {
                throw new InvalidMessageHandlerException(sprintf(
                    'Domain-event handler service "%s" returned a value.',
                    $serviceId,
                ));
            }
        }
    }
}
