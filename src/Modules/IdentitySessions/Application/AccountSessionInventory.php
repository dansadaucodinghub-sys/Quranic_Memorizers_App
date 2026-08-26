<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Application;

use Qmdb\Modules\IdentitySessions\Domain\Repository\DeviceInventoryRecord;
use Qmdb\Modules\IdentitySessions\Domain\Repository\SessionInventoryRecord;

final readonly class AccountSessionInventory
{
    /**
     * @param list<SessionInventoryRecord> $sessions
     * @param list<DeviceInventoryRecord> $devices
     */
    public function __construct(
        public array $sessions,
        public array $devices,
        public string $currentSessionId,
        public string $currentDeviceId,
    ) {
    }
}
