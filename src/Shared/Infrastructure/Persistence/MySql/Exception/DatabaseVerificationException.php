<?php

declare(strict_types=1);

namespace Qmdb\Shared\Infrastructure\Persistence\MySql\Exception;

use RuntimeException;
use Throwable;

final class DatabaseVerificationException extends RuntimeException
{
    public function __construct(private readonly string $safeCode, ?Throwable $previous = null)
    {
        parent::__construct('Database session verification failed.', 0, $previous);
    }

    public function safeCode(): string
    {
        return $this->safeCode;
    }
}
