<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Domain;

final readonly class PersonSearchName
{
    public function __construct(public string $value)
    {
    }
}
