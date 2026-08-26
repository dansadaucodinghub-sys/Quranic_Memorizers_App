<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Observability;

use Stringable;

final class ExplosiveStringable implements Stringable
{
    public bool $invoked = false;

    public function __toString(): string
    {
        $this->invoked = true;

        return 'must-not-run';
    }
}
