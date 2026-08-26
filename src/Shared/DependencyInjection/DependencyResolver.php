<?php

declare(strict_types=1);

namespace Qmdb\Shared\DependencyInjection;

interface DependencyResolver
{
    public function get(string $serviceId): object;
}
