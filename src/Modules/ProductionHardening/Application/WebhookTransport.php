<?php

declare(strict_types=1);

namespace Qmdb\Modules\ProductionHardening\Application;

interface WebhookTransport
{
    /**
     * @param array<string,string> $headers
     * @return array{status:int,error_code:?string}
     */
    public function send(string $url, string $payload, array $headers, int $timeoutSeconds): array;
}
