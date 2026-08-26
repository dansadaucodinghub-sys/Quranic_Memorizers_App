<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Domain;

use JsonSerializable;
use LogicException;

final readonly class DeviceCookieValue implements JsonSerializable
{
    public function __construct(public DeviceId $deviceId, private DeviceTokenSecret $secret)
    {
    }

    public function revealForCookie(): string
    {
        return 'v1.' . $this->deviceId->toString() . '.' . $this->secret->revealForCookie();
    }

    public function secretForVerification(): DeviceTokenSecret
    {
        return $this->secret;
    }

    /** @return array{value: string} */
    public function __debugInfo(): array
    {
        return ['value' => '[REDACTED]'];
    }

    public function jsonSerialize(): never
    {
        throw new LogicException('Device cookie values cannot be serialized.');
    }
}
