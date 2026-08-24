<?php

declare(strict_types=1);

namespace Qmdb\Shared\Time;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}
