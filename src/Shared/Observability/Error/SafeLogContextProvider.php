<?php

declare(strict_types=1);

namespace Qmdb\Shared\Observability\Error;

interface SafeLogContextProvider
{
    public function safeErrorCode(): string;

    /** @return array<string, mixed> */
    public function safeLogContext(): array;
}
