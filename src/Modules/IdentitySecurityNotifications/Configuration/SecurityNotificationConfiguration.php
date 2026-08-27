<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySecurityNotifications\Configuration;

use InvalidArgumentException;

final readonly class SecurityNotificationConfiguration
{
    public function __construct(
        public int $batchSize,
        public int $maximumAttempts,
        public int $leaseSeconds,
        public int $retryBaseSeconds,
        public int $retryMaximumSeconds,
    ) {
        if ($batchSize < 1 || $maximumAttempts < 1 || $leaseSeconds < 30) {
            throw new InvalidArgumentException('Security notification limits are invalid.');
        }
        if ($retryBaseSeconds < 0 || $retryMaximumSeconds < $retryBaseSeconds) {
            throw new InvalidArgumentException('Security notification retry limits are invalid.');
        }
    }
}
