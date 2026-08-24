<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Http;

final readonly class BootstrapHttpResponse
{
    /** @var array<string, string> */
    private const JSON_HEADERS = [
        'Content-Type' => 'application/json; charset=utf-8',
        'Cache-Control' => 'no-store',
        'X-Content-Type-Options' => 'nosniff',
    ];

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

    public static function configurationFailure(): self
    {
        return new self(
            statusCode: 500,
            headers: self::JSON_HEADERS,
            body: '{"application":"QMDB","status":"error","code":"CONFIGURATION_FAILURE"}',
        );
    }
}
