<?php

declare(strict_types=1);

namespace Qmdb\Modules\PilotOfflineRollout\Application;

use Qmdb\Modules\IdentitySessions\Application\AuthenticatedAccountContext;
use Qmdb\Modules\TenancyContext\Domain\AccountWorkspaceTenantContext;
use Qmdb\Shared\Identifier\UuidV7;

/** Explicit device-authenticated authority passed to prior-domain services. */
final readonly class OfflineOperationContext
{
    /** @param array<string,mixed> $payload */
    public function __construct(
        public AuthenticatedAccountContext $actor,
        public AccountWorkspaceTenantContext $tenant,
        public UuidV7 $changeId,
        public UuidV7 $entityId,
        public int $expectedVersion,
        public string $operation,
        public array $payload,
        public string $correlationId,
    ) {
        if ($expectedVersion < 1 || $operation === '') {
            throw new \InvalidArgumentException('Offline operation context is invalid.');
        }
    }

    public function requiredUuid(string $key): UuidV7
    {
        $value = $this->payload[$key] ?? null;
        if (!is_string($value)) {
            throw new \InvalidArgumentException('Offline operation UUID field is invalid.');
        }

        return UuidV7::fromString($value);
    }

    /** @param list<string> $keys */
    public function assertExactPayload(array $keys): void
    {
        $actual = array_keys($this->payload);
        sort($actual);
        sort($keys);
        if ($actual !== $keys) {
            throw new \InvalidArgumentException('Offline operation payload schema is invalid.');
        }
    }

    /** @return array<string,int> */
    public function scoreUnits(): array
    {
        $values = $this->payload['scores'] ?? null;
        if (!is_array($values) || $values === []) {
            throw new \InvalidArgumentException('Offline score entries are invalid.');
        }
        $result = [];
        foreach ($values as $code => $units) {
            if (!is_string($code) || preg_match('/\A[A-Z][A-Z0-9_]{1,63}\z/', $code) !== 1 || !is_int($units)) {
                throw new \InvalidArgumentException('Offline score entry is invalid.');
            }
            $result[$code] = $units;
        }

        return $result;
    }
}
