<?php

declare(strict_types=1);

namespace Qmdb\Shared\Application\Command;

use Qmdb\Shared\Application\Exception\InvalidMessageHandlerException;
use Qmdb\Shared\Application\Handler\HandlerResolver;

final readonly class SynchronousCommandBus implements CommandBus
{
    public function __construct(
        private CommandHandlerMap $handlers,
        private HandlerResolver $handlerResolver,
    ) {
    }

    public function dispatch(Command $command): void
    {
        $serviceId = $this->handlers->handlerServiceId($command::class);
        $handler = $this->handlerResolver->resolve($serviceId);
        if (!$handler instanceof CommandHandler || !is_callable($handler)) {
            throw new InvalidMessageHandlerException(sprintf(
                'Command handler service "%s" is invalid.',
                $serviceId,
            ));
        }

        $result = $handler($command);
        if ($result !== null) {
            throw new InvalidMessageHandlerException(sprintf(
                'Command handler service "%s" returned a value.',
                $serviceId,
            ));
        }
    }
}
