<?php

declare(strict_types=1);

namespace Qmdb\Shared\DependencyInjection;

final readonly class ServiceAlias
{
    public function __construct(
        public string $alias,
        public string $target,
        public string $ownerModuleId,
    ) {
    }
}
