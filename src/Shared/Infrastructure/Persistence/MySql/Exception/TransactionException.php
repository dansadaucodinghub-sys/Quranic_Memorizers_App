<?php

declare(strict_types=1);

namespace Qmdb\Shared\Infrastructure\Persistence\MySql\Exception;

use RuntimeException;
use Throwable;

class TransactionException extends RuntimeException
{
    public function __construct(string $message = 'Database transaction failed.', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
