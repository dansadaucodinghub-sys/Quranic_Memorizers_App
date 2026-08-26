<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Response;

final readonly class ResponseEmissionPlan
{
    /** @param list<array{line: string, replace: bool}> $headers */
    public function __construct(
        private int $statusCode,
        private array $headers,
        private bool $emitBody,
    ) {
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /** @return list<array{line: string, replace: bool}> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function shouldEmitBody(): bool
    {
        return $this->emitBody;
    }
}
