<?php

declare(strict_types=1);

namespace Qmdb\Shared\Application\Query;

use Qmdb\Shared\Application\Exception\DuplicateHandlerRegistrationException;
use Qmdb\Shared\Application\Exception\InvalidMessageHandlerException;

final class QueryHandlerRegistry
{
    /** @var array<string, QueryRegistration> */
    private array $registrations = [];
    private bool $frozen = false;

    public function register(QueryRegistration $registration): void
    {
        if ($this->frozen) {
            throw new InvalidMessageHandlerException('Query-handler registration is closed.');
        }
        if (
            !class_exists($registration->queryClass)
            || !is_subclass_of($registration->queryClass, Query::class)
        ) {
            throw new InvalidMessageHandlerException('Registered query class is invalid.');
        }
        self::assertHandlerId($registration->handlerServiceId);
        if (isset($this->registrations[$registration->queryClass])) {
            throw new DuplicateHandlerRegistrationException(sprintf(
                'Query "%s" already has a handler.',
                $registration->queryClass,
            ));
        }
        $this->registrations[$registration->queryClass] = $registration;
    }

    public function freeze(): QueryHandlerMap
    {
        $this->frozen = true;

        return new QueryHandlerMap($this->registrations);
    }

    private static function assertHandlerId(string $handlerServiceId): void
    {
        if ($handlerServiceId === '' || preg_match('/[\s\x00-\x1F\x7F]/', $handlerServiceId) === 1) {
            throw new InvalidMessageHandlerException('Query-handler service identifier is invalid.');
        }
    }
}
