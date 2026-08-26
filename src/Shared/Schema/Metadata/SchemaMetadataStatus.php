<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Metadata;

enum SchemaMetadataStatus: string
{
    case READY = 'READY';
    case NOT_INSTALLED = 'NOT_INSTALLED';
    case INCOMPATIBLE = 'INCOMPATIBLE';
    case INVALID = 'INVALID';
    case UNAVAILABLE = 'UNAVAILABLE';
}
