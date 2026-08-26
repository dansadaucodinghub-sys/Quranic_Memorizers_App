<?php

declare(strict_types=1);

namespace Qmdb\Shared\Observability\Error;

final readonly class BootstrapFailureResponse
{
    /** @param array<string, string> $headers */
    public function __construct(
        private int $status,
        private array $headers,
        private string $body,
        private string $requestId,
    ) {
    }

    public function status(): int
    {
        return $this->status;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function requestId(): string
    {
        return $this->requestId;
    }
}
