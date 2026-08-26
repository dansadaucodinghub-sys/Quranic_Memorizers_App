<?php

declare(strict_types=1);

namespace Qmdb\Modules\IdentityAccess\Security\Peer;

final readonly class DirectPeerAddress
{
    private function __construct(private string $value)
    {
    }

    public static function fromObserved(?string $value): self
    {
        if ($value === null || filter_var($value, FILTER_VALIDATE_IP) === false) {
            return new self('unknown-peer');
        }

        return new self(strtolower($value));
    }

    public function fingerprintInput(): string
    {
        return $this->value;
    }

    /** @return array{value: string} */
    public function __debugInfo(): array
    {
        return ['value' => '[REDACTED]'];
    }
}
