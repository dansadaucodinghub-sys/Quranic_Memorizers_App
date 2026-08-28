<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Contract;

use Throwable;

interface SafeHttpException extends Throwable
{
    public function statusCode(): int;

    public function safeCode(): string;
}
