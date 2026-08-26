<?php

declare(strict_types=1);

namespace Qmdb\Shared\DependencyInjection;

use Psr\Container\ContainerExceptionInterface;
use RuntimeException;

class DependencyInjectionException extends RuntimeException implements ContainerExceptionInterface
{
}
