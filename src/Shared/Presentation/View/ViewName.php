<?php

declare(strict_types=1);

namespace Qmdb\Shared\Presentation\View;

use InvalidArgumentException;

final readonly class ViewName
{
    public function __construct(private string $value)
    {
        if (strlen($value) > 100 || preg_match('/^[a-z][a-z0-9-]*(?:\.[a-z][a-z0-9-]*)+$/D', $value) !== 1) {
            throw new InvalidArgumentException('View name is invalid.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }
}
