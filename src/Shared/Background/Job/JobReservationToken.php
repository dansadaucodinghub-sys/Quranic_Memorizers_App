<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Job;

use InvalidArgumentException;
use LogicException;

final readonly class JobReservationToken
{
    public function __construct(private string $value)
    {
        if (strlen($value) < 16 || strlen($value) > 256 || preg_match('/[\x00-\x20\x7F]/', $value) === 1) {
            throw new InvalidArgumentException('Job reservation token is invalid.');
        }
    }

    public function matches(self $other): bool
    {
        return hash_equals($this->value, $other->value);
    }

    public function revealToSource(): string
    {
        return $this->value;
    }

    /** @return array{reservation_token: string} */
    public function __debugInfo(): array
    {
        return ['reservation_token' => '[REDACTED]'];
    }

    /** @return array<never, never> */
    public function __serialize(): array
    {
        throw new LogicException('Reservation tokens cannot be serialized.');
    }
}
