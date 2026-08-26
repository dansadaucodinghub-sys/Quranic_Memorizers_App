<?php

declare(strict_types=1);

namespace Qmdb\Shared\Observability\Logging;

use Psr\Log\LoggerInterface;

interface StructuredLoggerFactory
{
    public function create(): LoggerInterface;
}
