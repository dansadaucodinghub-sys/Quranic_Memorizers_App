<?php

declare(strict_types=1);

namespace Qmdb\Shared\DependencyInjection;

use Closure;

final readonly class ClosureServiceFactory implements ServiceFactory
{
    /** @param Closure(DependencyResolver): object $factory */
    public function __construct(private Closure $factory)
    {
    }

    public function create(DependencyResolver $resolver): object
    {
        return ($this->factory)($resolver);
    }
}
