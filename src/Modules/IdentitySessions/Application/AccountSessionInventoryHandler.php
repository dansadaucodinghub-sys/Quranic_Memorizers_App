<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Application;

use Qmdb\Modules\IdentitySessions\Domain\Repository\UserDeviceRepository;
use Qmdb\Modules\IdentitySessions\Domain\Repository\UserSessionRepository;

final readonly class AccountSessionInventoryHandler
{
    public function __construct(
        private UserSessionRepository $sessions,
        private UserDeviceRepository $devices,
    ) {
    }

    public function handle(AuthenticatedAccountContext $context): AccountSessionInventory
    {
        return new AccountSessionInventory(
            $this->sessions->listSessionsForAccount($context->accountInternalId),
            $this->devices->listDevicesForAccount($context->accountInternalId),
            $context->sessionId->toString(),
            $context->deviceId->toString(),
        );
    }
}
