<?php

declare(strict_types=1);

namespace Qmdb\Shared\Observability\Error;

final readonly class NativeLastErrorProvider implements LastErrorProvider
{
    public function lastError(): ?array
    {
        return error_get_last();
    }
}
