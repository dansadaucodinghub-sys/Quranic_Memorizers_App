<?php

declare(strict_types=1);

namespace Qmdb\Shared\Application\Command;

use Qmdb\Shared\Application\Exception\UnhandledMessageException;

final readonly class CommandHandlerMap
{
    /** @param array<string, CommandRegistration> $registrations */
    public function __construct(private array $registrations)
    {
    }

    public function handlerServiceId(string $commandClass): string
    {
        return $this->registrations[$commandClass]->handlerServiceId
            ?? throw new UnhandledMessageException(sprintf(
                'No command handler is registered for "%s".',
                $commandClass,
            ));
    }

    /** @return list<string> */
    public function handlerServiceIds(): array
    {
        return array_values(array_map(
            static fn (CommandRegistration $registration): string => $registration->handlerServiceId,
            $this->registrations,
        ));
    }
}
