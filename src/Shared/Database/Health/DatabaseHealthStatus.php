<?php

declare(strict_types=1);

namespace Qmdb\Shared\Database\Health;

enum DatabaseHealthStatus: string
{
    case READY = 'READY';
    case UNAVAILABLE = 'UNAVAILABLE';
    case INVALID_SESSION = 'INVALID_SESSION';
    case INCOMPATIBLE_SERVER = 'INCOMPATIBLE_SERVER';
    case TLS_REQUIRED = 'TLS_REQUIRED';
    case CREDENTIAL_FAILURE = 'CREDENTIAL_FAILURE';
}
