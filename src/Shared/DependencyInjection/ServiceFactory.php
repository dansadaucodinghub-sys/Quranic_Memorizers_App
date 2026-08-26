<?php

declare(strict_types=1);

namespace Qmdb\Shared\DependencyInjection;

interface ServiceFactory
{
    public function create(DependencyResolver $resolver): object;
}
