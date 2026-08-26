<?php

declare(strict_types=1);

namespace Qmdb\Shared\Application\Handler;

interface HandlerResolver
{
    public function resolve(string $serviceId): object;
}
