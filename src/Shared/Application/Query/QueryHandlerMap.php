<?php

declare(strict_types=1);

namespace Qmdb\Shared\Application\Query;

use Qmdb\Shared\Application\Exception\UnhandledMessageException;

final readonly class QueryHandlerMap
{
    /** @param array<string, QueryRegistration> $registrations */
    public function __construct(private array $registrations)
    {
    }

    public function handlerServiceId(string $queryClass): string
    {
        return $this->registrations[$queryClass]->handlerServiceId
            ?? throw new UnhandledMessageException(sprintf(
                'No query handler is registered for "%s".',
                $queryClass,
            ));
    }

    /** @return list<string> */
    public function handlerServiceIds(): array
    {
        return array_values(array_map(
            static fn (QueryRegistration $registration): string => $registration->handlerServiceId,
            $this->registrations,
        ));
    }
}
