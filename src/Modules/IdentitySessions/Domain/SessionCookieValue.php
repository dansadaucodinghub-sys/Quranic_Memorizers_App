<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentitySessions\Domain;

use JsonSerializable;
use LogicException;

final readonly class SessionCookieValue implements JsonSerializable
{
    public function __construct(public SessionId $sessionId, private SessionTokenSecret $secret)
    {
    }

    public function revealForCookie(): string
    {
        return 'v1.' . $this->sessionId->toString() . '.' . $this->secret->revealForCookie();
    }

    public function secretForVerification(): SessionTokenSecret
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
        throw new LogicException('Session cookie values cannot be serialized.');
    }
}
