<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap\Http;

use JsonException;
use Qmdb\Bootstrap\Application;

final readonly class BootstrapHttpApplication
{
    /** @var array<string, string> */
    private const RESPONSE_HEADERS = [
        'Content-Type' => 'application/json; charset=utf-8',
        'Cache-Control' => 'no-store',
        'X-Content-Type-Options' => 'nosniff',
    ];

    public function __construct(private Application $application)
    {
    }

    /**
     * @param list<string>|null $loadedExtensions
     * @throws JsonException
     */
    public function handle(
        ?string $phpVersion = null,
        ?array $loadedExtensions = null,
    ): BootstrapHttpResponse {
        $runtimeResult = $this->application->validateRuntime($phpVersion, $loadedExtensions);

        if (!$runtimeResult->isSatisfied()) {
            return $this->failureResponse();
        }

        $metadata = $this->application->metadata();
        $body = json_encode(
            [
                'application' => $metadata->applicationCode(),
                'status' => 'ready',
                'phase' => $metadata->currentPhase(),
                'batch' => $metadata->currentBatch(),
                'baseline' => $metadata->frozenBaseline(),
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        return new BootstrapHttpResponse(
            statusCode: 200,
            headers: self::RESPONSE_HEADERS,
            body: $body,
        );
    }

    /** @throws JsonException */
    private function failureResponse(): BootstrapHttpResponse
    {
        $body = json_encode(
            [
                'application' => $this->application->metadata()->applicationCode(),
                'status' => 'error',
                'code' => 'BOOTSTRAP_FAILURE',
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        return new BootstrapHttpResponse(
            statusCode: 500,
            headers: self::RESPONSE_HEADERS,
            body: $body,
        );
    }
}
