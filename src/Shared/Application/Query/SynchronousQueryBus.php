<?php

declare(strict_types=1);

namespace Qmdb\Shared\Application\Query;

use Qmdb\Shared\Application\Exception\InvalidMessageHandlerException;
use Qmdb\Shared\Application\Handler\HandlerResolver;

final readonly class SynchronousQueryBus implements QueryBus
{
    public function __construct(
        private QueryHandlerMap $handlers,
        private HandlerResolver $handlerResolver,
    ) {
    }

    public function ask(Query $query): mixed
    {
        $serviceId = $this->handlers->handlerServiceId($query::class);
        $handler = $this->handlerResolver->resolve($serviceId);
        if (!$handler instanceof QueryHandler || !is_callable($handler)) {
            throw new InvalidMessageHandlerException(sprintf(
                'Query handler service "%s" is invalid.',
                $serviceId,
            ));
        }

        return $handler($query);
    }
}
