<?php

declare(strict_types=1);

namespace Qmdb\Shared\Configuration\Database;

use InvalidArgumentException;

final class DatabaseConfigurationException extends InvalidArgumentException
{
    public function __construct(
        private readonly string $safeCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function safeCode(): string
    {
        return $this->safeCode;
    }
}
