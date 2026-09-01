<?php

declare(strict_types=1);

namespace Qmdb\Modules\People\Application;

final readonly class PersonProfileResult
{
    public function __construct(public string $personPublicId, public int $version, public bool $replayed = false)
    {
    }
}
