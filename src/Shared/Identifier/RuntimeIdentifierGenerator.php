<?php

declare(strict_types=1);

namespace Qmdb\Shared\Identifier;

interface RuntimeIdentifierGenerator
{
    public function generate(): RuntimeIdentifier;
}
