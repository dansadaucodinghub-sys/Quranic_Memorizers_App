<?php

declare(strict_types=1);

namespace Qmdb\Modules\PilotOfflineRollout\Application;

use Qmdb\Shared\Identifier\UuidV7;

/** Closed, public-identifier-only result returned by an authoritative offline handler. */
final readonly class OfflineOperationResult
{
    private function __construct(
        public string $status,
        public UuidV7 $entityId,
        public ?int $version,
        public string $state,
        public ?string $reasonCode,
    ) {
        if (!in_array($status, ['ACCEPTED', 'DUPLICATE', 'CONFLICT', 'REJECTED'], true)) {
            throw new \InvalidArgumentException('Offline operation result status is invalid.');
        }
    }

    public static function accepted(UuidV7 $entityId, int $version, string $state): self
    {
        return new self('ACCEPTED', $entityId, $version, $state, null);
    }

    public static function duplicate(UuidV7 $entityId, int $version, string $state): self
    {
        return new self('DUPLICATE', $entityId, $version, $state, null);
    }

    public static function conflict(UuidV7 $entityId, string $reasonCode, ?int $version = null): self
    {
        return new self('CONFLICT', $entityId, $version, 'CONFLICT', $reasonCode);
    }

    public static function rejected(UuidV7 $entityId, string $reasonCode): self
    {
        return new self('REJECTED', $entityId, null, 'REJECTED', $reasonCode);
    }
}
