<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Http;

final readonly class BootstrapHttpResponse
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private int $statusCode,
        private array $headers,
        private string $body,
    ) {
    }

    public function statusCode(): int
    {
        return $this->statusCode;
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
}
