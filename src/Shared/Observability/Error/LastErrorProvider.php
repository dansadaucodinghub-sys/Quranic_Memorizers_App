<?php

declare(strict_types=1);

namespace Qmdb\Shared\Observability\Error;

interface LastErrorProvider
{
    /** @return array{type: int, message: string, file: string, line: int}|null */
    public function lastError(): ?array;
}
