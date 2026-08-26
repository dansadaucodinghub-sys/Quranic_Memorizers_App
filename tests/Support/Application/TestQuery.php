<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Application;

use Qmdb\Shared\Application\Query\Query;

final readonly class TestQuery implements Query
{
    public function __construct(public string $value)
    {
    }
}
