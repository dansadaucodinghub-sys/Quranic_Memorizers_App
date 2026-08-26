<?php

declare(strict_types=1);

namespace Qmdb\Shared\Application\Command;

use Qmdb\Shared\Application\Exception\DuplicateHandlerRegistrationException;
use Qmdb\Shared\Application\Exception\InvalidMessageHandlerException;

final class CommandHandlerRegistry
{
    /** @var array<string, CommandRegistration> */
    private array $registrations = [];
    private bool $frozen = false;

    public function register(CommandRegistration $registration): void
    {
        if ($this->frozen) {
            throw new InvalidMessageHandlerException('Command-handler registration is closed.');
        }
        if (
            !class_exists($registration->commandClass)
            || !is_subclass_of($registration->commandClass, Command::class)
        ) {
            throw new InvalidMessageHandlerException('Registered command class is invalid.');
        }
        self::assertHandlerId($registration->handlerServiceId);
        if (isset($this->registrations[$registration->commandClass])) {
            throw new DuplicateHandlerRegistrationException(sprintf(
                'Command "%s" already has a handler.',
                $registration->commandClass,
            ));
        }
        $this->registrations[$registration->commandClass] = $registration;
    }

    public function freeze(): CommandHandlerMap
    {
        $this->frozen = true;

        return new CommandHandlerMap($this->registrations);
    }

    private static function assertHandlerId(string $handlerServiceId): void
    {
        if ($handlerServiceId === '' || preg_match('/[\s\x00-\x1F\x7F]/', $handlerServiceId) === 1) {
            throw new InvalidMessageHandlerException('Command-handler service identifier is invalid.');
        }
    }
}
