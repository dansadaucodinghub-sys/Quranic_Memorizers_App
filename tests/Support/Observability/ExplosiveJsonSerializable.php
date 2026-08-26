<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Observability;

use JsonSerializable;

final class ExplosiveJsonSerializable implements JsonSerializable
{
    public bool $invoked = false;

    public function jsonSerialize(): mixed
    {
        $this->invoked = true;

        return ['secret' => 'must-not-run'];
    }
}
