<?php

declare(strict_types=1);

namespace Qmdb\Shared\Observability\Correlation;

interface CorrelationIdGenerator
{
    public function generate(): CorrelationId;
}
