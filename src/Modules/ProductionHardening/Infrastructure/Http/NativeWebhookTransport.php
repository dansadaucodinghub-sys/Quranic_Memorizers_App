<?php

declare(strict_types=1);

namespace Qmdb\Modules\ProductionHardening\Infrastructure\Http;

use Qmdb\Modules\ProductionHardening\Application\WebhookTransport;
use Qmdb\Modules\ProductionHardening\Domain\WebhookEndpointPolicy;

final readonly class NativeWebhookTransport implements WebhookTransport
{
    public function __construct(private WebhookEndpointPolicy $endpoints)
    {
    }

    /** @return array{status:int,error_code:?string} */
    public function send(string $url, string $payload, array $headers, int $timeoutSeconds): array
    {
        $this->endpoints->assertAllowed($url);
        if (strlen($payload) > 262144) {
            throw new \InvalidArgumentException('Webhook payload exceeds the 256 KiB limit.');
        }
        $lines = [];
        foreach ($headers as $name => $value) {
            if (preg_match('/\A[A-Za-z0-9-]{1,64}\z/', $name) !== 1 || str_contains($value, "\r") || str_contains($value, "\n")) {
                throw new \InvalidArgumentException('Webhook header is invalid.');
            }
            $lines[] = $name . ': ' . $value;
        }
        $context = stream_context_create(['http' => [
            'method' => 'POST', 'header' => implode("\r\n", $lines), 'content' => $payload,
            'timeout' => max(1, min(10, $timeoutSeconds)), 'ignore_errors' => true,
            'max_redirects' => 0, 'follow_location' => 0,
        ]]);
        $result = @file_get_contents($url, false, $context, 0, 8192);
        $responseHeaders = http_get_last_response_headers() ?? [];
        $status = 0;
        if (
            is_string($responseHeaders[0] ?? null)
            && preg_match('/\s([1-5][0-9]{2})\s/', $responseHeaders[0], $matches) === 1
        ) {
            $status = (int) $matches[1];
        }
        if ($result === false && $status === 0) {
            return ['status' => 0, 'error_code' => 'TRANSPORT_FAILURE'];
        }
        return ['status' => $status, 'error_code' => null];
    }
}
