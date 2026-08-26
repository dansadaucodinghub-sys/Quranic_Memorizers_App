<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Seed;

enum SeedStatus: string
{
    case RUNNING = 'RUNNING';
    case APPLIED = 'APPLIED';
    case FAILED = 'FAILED';
    case DRIFTED = 'DRIFTED';
}
