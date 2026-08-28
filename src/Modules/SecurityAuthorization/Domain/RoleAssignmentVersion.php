<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain;

use InvalidArgumentException;

final readonly class RoleAssignmentVersion
{
    public function __construct(public int $value)
    {
        if ($value < 1) {
            throw new InvalidArgumentException('Role-assignment version must be positive.');
        }
    }

    public function next(): self
    {
        return new self($this->value + 1);
    }
}
