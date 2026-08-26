<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Observability;

use Qmdb\Shared\Observability\Error\SafeLogContextProvider;
use RuntimeException;

final class SafeTestException extends RuntimeException implements SafeLogContextProvider
{
    public function safeErrorCode(): string
    {
        return 'SAFE_TEST_FAILURE';
    }

    public function safeLogContext(): array
    {
        return ['operation' => 'test', 'authorization' => 'QMDB_MUST_BE_REDACTED'];
    }
}
