<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Configuration;

use InvalidArgumentException;

final readonly class IdentitySessionConfiguration
{
    public string $sessionCookieName;
    public string $deviceCookieName;

    public function __construct(
        public bool $productionLike,
        public int $idleTtlSeconds,
        public int $absoluteTtlSeconds,
        public int $rotationIntervalSeconds,
        public int $previousTokenGraceSeconds,
        public int $touchIntervalSeconds,
        public int $maximumActiveSessions,
        public int $deviceCookieTtlSeconds,
    ) {
        if ($idleTtlSeconds < 300) {
            throw new InvalidArgumentException('Session idle TTL must be at least 300 seconds.');
        }
        if ($absoluteTtlSeconds < $idleTtlSeconds) {
            throw new InvalidArgumentException('Session absolute TTL cannot be lower than idle TTL.');
        }
        if ($rotationIntervalSeconds < 1 || $rotationIntervalSeconds >= $absoluteTtlSeconds) {
            throw new InvalidArgumentException('Session rotation interval is invalid.');
        }
        if ($previousTokenGraceSeconds < 0 || $previousTokenGraceSeconds >= $rotationIntervalSeconds) {
            throw new InvalidArgumentException('Previous-token grace is invalid.');
        }
        if ($touchIntervalSeconds < 0 || $maximumActiveSessions < 1) {
            throw new InvalidArgumentException('Session touch or active-session limit is invalid.');
        }
        if ($deviceCookieTtlSeconds < 86400 || $deviceCookieTtlSeconds > 63072000) {
            throw new InvalidArgumentException('Device-cookie TTL is outside the supported range.');
        }
        $this->sessionCookieName = $productionLike ? '__Host-qmdb_session' : 'qmdb_session';
        $this->deviceCookieName = $productionLike ? '__Host-qmdb_device' : 'qmdb_device';
    }
}
