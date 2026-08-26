<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Background;

use Qmdb\Shared\Identifier\RuntimeIdentifier;
use Qmdb\Shared\Identifier\RuntimeIdentifierGenerator;
use UnderflowException;

final class SequenceRuntimeIdentifierGenerator implements RuntimeIdentifierGenerator
{
    /** @param list<string> $values */
    public function __construct(private array $values)
    {
    }

    public function generate(): RuntimeIdentifier
    {
        $value = array_shift($this->values)
            ?? throw new UnderflowException('No runtime identifier remains.');

        return RuntimeIdentifier::fromString($value);
    }
}
