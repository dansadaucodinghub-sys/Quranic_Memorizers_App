<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Application;

use DateTimeImmutable;
use JsonSerializable;
use LogicException;
use Qmdb\Modules\Identity\Domain\Value\AccountId;
use Qmdb\Modules\IdentitySessions\Domain\DeviceId;
use Qmdb\Modules\IdentitySessions\Domain\SessionId;

final readonly class AuthenticatedAccountContext implements JsonSerializable
{
    public function __construct(
        public int $accountInternalId,
        public AccountId $accountId,
        public int $sessionInternalId,
        public SessionId $sessionId,
        public int $deviceInternalId,
        public DeviceId $deviceId,
        public DateTimeImmutable $authenticatedAt,
        public int $sessionVersion,
    ) {
    }

    public function jsonSerialize(): never
    {
        throw new LogicException('Authenticated account context cannot be serialized.');
    }
}
