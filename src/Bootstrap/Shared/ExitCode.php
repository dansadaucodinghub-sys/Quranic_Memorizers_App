<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Shared;

enum ExitCode: int
{
    case SUCCESS = 0;
    case FAILURE = 1;
    case INVALID_USAGE = 64;
}
