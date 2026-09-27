<?php

declare(strict_types=1);

namespace Qmdb\Modules\ProductionHardening\Domain;

final readonly class WebhookEndpointPolicy
{
    public function assertAllowed(string $url): void
    {
        if (strlen($url) > 1000 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException('Webhook endpoint is not a valid bounded URL.');
        }
        $parts = parse_url($url);
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            throw new \InvalidArgumentException('Webhook endpoint must use HTTPS.');
        }
        if (isset($parts['user'], $parts['pass']) || isset($parts['fragment'])) {
            throw new \InvalidArgumentException('Webhook endpoint must not contain credentials or a fragment.');
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            throw new \InvalidArgumentException('Webhook endpoint host is not allowed.');
        }
        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : $this->resolve($host);
        if ($addresses === []) {
            throw new \InvalidArgumentException('Webhook endpoint host cannot be resolved safely.');
        }
        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new \InvalidArgumentException('Webhook endpoint resolves to a private or reserved address.');
            }
        }
    }

    /** @return list<string> */
    private function resolve(string $host): array
    {
        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        if (!is_array($records)) {
            return [];
        }
        $addresses = [];
        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($address)) {
                $addresses[] = $address;
            }
        }
        return array_values(array_unique($addresses));
    }
}
