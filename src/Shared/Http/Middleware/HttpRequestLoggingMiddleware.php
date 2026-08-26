<?php

declare(strict_types=1);

namespace Qmdb\Shared\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Qmdb\Shared\Configuration\Logging\LogLevel;
use Qmdb\Shared\Http\Request\RequestContextAttributes;
use Qmdb\Shared\Observability\Correlation\CorrelationId;
use Qmdb\Shared\Observability\Logging\EventLogger;
use Qmdb\Shared\Observability\Logging\LogEventName;
use Qmdb\Shared\Time\MonotonicClock;
use Throwable;

final readonly class HttpRequestLoggingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private EventLogger $eventLogger,
        private MonotonicClock $clock,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $correlationId = $request->getAttribute(RequestContextAttributes::REQUEST_ID);
        if (!$correlationId instanceof CorrelationId) {
            return $handler->handle($request);
        }

        $method = strtoupper($request->getMethod());
        $startedAt = $this->clock->nanoseconds();
        $this->safeLog(LogLevel::INFO, 'http.request.started', [
            'request_id' => $correlationId->value(),
            'method' => $method,
        ]);

        $response = $handler->handle($request);
        $duration = max(0, $this->clock->nanoseconds() - $startedAt) / 1_000_000;
        $this->safeLog($this->levelForStatus($response->getStatusCode()), 'http.request.completed', [
            'request_id' => $correlationId->value(),
            'method' => $method,
            'status' => $response->getStatusCode(),
            'duration_ms' => round($duration, 3),
        ]);

        return $response;
    }

    /** @param array<string, mixed> $context */
    private function safeLog(LogLevel $level, string $event, array $context): void
    {
        try {
            $this->eventLogger->log($level, new LogEventName($event), $context);
        } catch (Throwable) {
            // The logging boundary is non-authoritative and must preserve the response.
        }
    }

    private function levelForStatus(int $status): LogLevel
    {
        if ($status >= 500) {
            return LogLevel::ERROR;
        }

        if ($status === 429) {
            return LogLevel::WARNING;
        }

        if ($status >= 400) {
            return $status === 404 ? LogLevel::INFO : LogLevel::NOTICE;
        }

        return LogLevel::INFO;
    }
}
