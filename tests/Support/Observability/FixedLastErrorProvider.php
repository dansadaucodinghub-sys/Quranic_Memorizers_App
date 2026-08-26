<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Observability;

use Qmdb\Shared\Observability\Error\LastErrorProvider;

final readonly class FixedLastErrorProvider implements LastErrorProvider
{
    /** @param array{type: int, message: string, file: string, line: int}|null $error */
    public function __construct(private ?array $error)
    {
    }

    public function lastError(): ?array
    {
        return $this->error;
    }
}
