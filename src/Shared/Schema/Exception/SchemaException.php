<?php

declare(strict_types=1);

namespace Qmdb\Shared\Schema\Exception;

use RuntimeException;
use Throwable;

class SchemaException extends RuntimeException
{
    public function __construct(
        private readonly string $safeCode,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function safeCode(): string
    {
        return $this->safeCode;
    }
}
