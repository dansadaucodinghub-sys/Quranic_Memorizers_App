<?php

declare(strict_types=1);

namespace Qmdb\Shared\Application\Query;

final readonly class QueryRegistration
{
    public function __construct(
        public string $queryClass,
        public string $handlerServiceId,
        public string $ownerModuleId,
    ) {
    }
}
