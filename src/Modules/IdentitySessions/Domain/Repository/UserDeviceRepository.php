<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Domain\Repository;

use DateTimeImmutable;
use Qmdb\Modules\IdentitySessions\Domain\DeviceId;
use Qmdb\Modules\IdentitySessions\Domain\DeviceTokenHash;

interface UserDeviceRepository
{
    public function findForAccount(int $accountInternalId, DeviceId $deviceId): ?DeviceAuthenticationRecord;

    public function createDevice(
        int $accountInternalId,
        DeviceId $deviceId,
        DeviceTokenHash $tokenHash,
        DateTimeImmutable $now,
    ): DeviceAuthenticationRecord;

    public function touchDevice(
        int $accountInternalId,
        int $deviceInternalId,
        int $expectedVersion,
        DateTimeImmutable $now,
    ): bool;

    public function revoke(
        int $accountInternalId,
        DeviceId $deviceId,
        int $expectedVersion,
        DateTimeImmutable $now,
    ): bool;

    /** @return list<DeviceInventoryRecord> */
    public function listDevicesForAccount(int $accountInternalId, int $limit = 50): array;
}
