<?php

declare(strict_types=1);

namespace Qmdb\Shared\Observability\Correlation;

use Qmdb\Shared\Identifier\RuntimeIdentifierGenerator;

final readonly class SecureCorrelationIdGenerator implements CorrelationIdGenerator
{
    public function __construct(private RuntimeIdentifierGenerator $identifierGenerator)
    {
    }

    public function generate(): CorrelationId
    {
        return new CorrelationId($this->identifierGenerator->generate()->value());
    }
}
