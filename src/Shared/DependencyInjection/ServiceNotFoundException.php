<?php

declare(strict_types=1);

namespace Qmdb\Shared\DependencyInjection;

use Psr\Container\NotFoundExceptionInterface;

final class ServiceNotFoundException extends DependencyInjectionException implements NotFoundExceptionInterface
{
}
