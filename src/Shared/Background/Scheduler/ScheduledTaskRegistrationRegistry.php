<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Scheduler;

use LogicException;

final class ScheduledTaskRegistrationRegistry
{
    /** @var array<string, ScheduledTaskRegistration> */
    private array $registrations = [];
    private bool $frozen = false;

    public function register(ScheduledTaskRegistration $registration): void
    {
        if ($this->frozen) {
            throw new LogicException('Scheduled task registration is frozen.');
        }
        $id = $registration->id->value();
        if (isset($this->registrations[$id])) {
            throw new LogicException('Duplicate scheduled task ID.');
        }
        $this->registrations[$id] = $registration;
    }

    /** @return list<ScheduledTaskRegistration> */
    public function freeze(): array
    {
        $this->frozen = true;
        ksort($this->registrations);

        return array_values($this->registrations);
    }
}
