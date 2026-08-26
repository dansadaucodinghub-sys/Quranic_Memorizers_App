<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Domain;

use InvalidArgumentException;

final readonly class DeviceCookieParser
{
    public function parse(string $value): DeviceCookieValue
    {
        if (strlen($value) > 128 || preg_match('/[\x00-\x20\x7f]/', $value) === 1) {
            throw new InvalidArgumentException('Device cookie is invalid.');
        }
        $parts = explode('.', $value);
        if (count($parts) !== 3 || $parts[0] !== 'v1') {
            throw new InvalidArgumentException('Device cookie is invalid.');
        }

        return new DeviceCookieValue(DeviceId::fromString($parts[1]), new DeviceTokenSecret($parts[2]));
    }
}
