<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Observability;

use Qmdb\Shared\Observability\Correlation\CorrelationId;
use Qmdb\Shared\Observability\Correlation\CorrelationIdGenerator;
use UnderflowException;

final class SequenceCorrelationIdGenerator implements CorrelationIdGenerator
{
    /** @param list<string> $values */
    public function __construct(private array $values)
    {
    }

    public function generate(): CorrelationId
    {
        $value = array_shift($this->values)
            ?? throw new UnderflowException('No correlation identifier remains.');

        return new CorrelationId($value);
    }
}
