<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Domain;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final readonly class PersonBirthDate
{
    private function __construct(private DateTimeImmutable $value)
    {
    }

    public static function fromString(string $value, DateTimeImmutable $now, int $maximumAgeYears): self
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Person birth date is invalid.');
        }
        $today = $now->setTimezone(new DateTimeZone('UTC'))->setTime(0, 0);
        if ($date > $today || $date < $today->modify('-' . $maximumAgeYears . ' years')) {
            throw new InvalidArgumentException('Person birth date is outside the approved range.');
        }

        return new self($date);
    }

    public function isBelowAge(int $years, DateTimeImmutable $now): bool
    {
        $today = $now->setTimezone(new DateTimeZone('UTC'))->setTime(0, 0);

        return $this->value > $today->modify('-' . $years . ' years');
    }

    public function value(): string
    {
        return $this->value->format('Y-m-d');
    }
}
