<?php

declare(strict_types=1);

namespace Qmdb\Shared\Infrastructure\Persistence\MySql\Connection;

final readonly class MySqlVerificationReport
{
    /** @param list<string> $verifiedInvariants */
    public function __construct(private array $verifiedInvariants)
    {
    }

    /** @return list<string> */
    public function verifiedInvariants(): array
    {
        return $this->verifiedInvariants;
    }
}
