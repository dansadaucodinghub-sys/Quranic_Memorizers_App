<?php

declare(strict_types=1);

namespace Qmdb\Shared\Observability\Error;

use Qmdb\Shared\Observability\Correlation\CorrelationIdGenerator;

final readonly class BootstrapFailureResponder
{
    public function __construct(
        private CorrelationIdGenerator $correlationIdGenerator,
        private BootstrapFailureReporter $reporter,
    ) {
    }

    public function create(string $classification): BootstrapFailureResponse
    {
        $correlationId = $this->correlationIdGenerator->generate();
        $this->reporter->report($correlationId, $classification);
        $requestId = $correlationId->value();
        $body = json_encode([
            'type' => 'about:blank',
            'title' => 'Internal Server Error',
            'status' => 500,
            'code' => 'BOOTSTRAP_FAILURE',
            'request_id' => $requestId,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return new BootstrapFailureResponse(
            500,
            [
                'Content-Type' => 'application/problem+json; charset=utf-8',
                'Cache-Control' => 'no-store',
                'X-Request-ID' => $requestId,
                'X-Content-Type-Options' => 'nosniff',
                'Referrer-Policy' => 'no-referrer',
                'X-Frame-Options' => 'DENY',
                'X-Permitted-Cross-Domain-Policies' => 'none',
                'Cross-Origin-Resource-Policy' => 'same-origin',
                'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
                'Content-Security-Policy' => "default-src 'none'; base-uri 'none'; "
                    . "frame-ancestors 'none'; form-action 'none'",
            ],
            $body,
            $requestId,
        );
    }
}
