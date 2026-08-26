<?php

declare(strict_types=1);

namespace Qmdb\Shared\Infrastructure\Persistence\MySql\Exception;

use RuntimeException;
use Throwable;

final class DatabaseConnectionException extends RuntimeException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('Database connection could not be established.', 0, $previous);
    }

    public function safeCode(): string
    {
        return 'DB_CONNECTION_FAILED';
    }
}
